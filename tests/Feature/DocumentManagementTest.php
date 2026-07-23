<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\ProjectRole;
use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectMembership;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_three_default_active_templates(): void
    {
        $this->assertSame(3, DocumentTemplate::query()->where('is_active', true)->count());
        foreach (DocumentType::cases() as $type) {
            $this->assertDatabaseHas('document_templates', [
                'type' => $type->value,
                'is_active' => true,
            ]);
        }
    }

    public function test_member_sees_only_documents_overview_projects_they_can_access(): void
    {
        $member = User::factory()->create();
        $visible = Project::factory()->create(['name' => 'Projeto documental visível']);
        $hidden = Project::factory()->create(['name' => 'Projeto documental restrito']);
        $this->addMember($visible, $member, ProjectRole::Observer);

        $this->actingAs($member)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);
    }

    public function test_manager_can_save_document_information_but_observer_cannot(): void
    {
        $project = Project::factory()->create();
        $manager = User::factory()->create();
        $observer = User::factory()->create();
        $this->addMember($project, $manager, ProjectRole::ProjectManager);
        $this->addMember($project, $observer, ProjectRole::Observer);
        $data = $this->visionData();

        $this->actingAs($manager)
            ->put(route('projects.documents.setup.update', $project), $data)
            ->assertSessionHasNoErrors();

        $this->assertSame($data['document_context'], $project->fresh()->document_context);

        $this->actingAs($observer)
            ->put(route('projects.documents.setup.update', $project), $data)
            ->assertForbidden();
    }

    public function test_incomplete_vision_redirects_to_information_form(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $template = DocumentTemplate::query()->where('type', DocumentType::Vision->value)->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('projects.documents.generate', $project), [
                'document_template_id' => $template->id,
            ])
            ->assertRedirect(route('projects.documents.setup.edit', $project))
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('project_documents', 0);
    }

    public function test_generation_creates_docx_pdf_history_and_sequential_versions(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create($this->visionData());
        Requirement::factory()->create(['project_id' => $project->id]);
        Task::factory()->create(['project_id' => $project->id]);
        $template = DocumentTemplate::query()->where('type', DocumentType::Vision->value)->firstOrFail();

        foreach ([1, 2] as $expectedVersion) {
            $response = $this->actingAs($administrator)
                ->post(route('projects.documents.generate', $project), [
                    'document_template_id' => $template->id,
                ]);

            $response
                ->assertRedirect(route('projects.documents.index', $project))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success');

            $document = ProjectDocument::query()->latest('version')->firstOrFail();
            $this->assertSame($expectedVersion, $document->version);
            Storage::disk('local')->assertExists($document->docx_path);
            Storage::disk('local')->assertExists($document->pdf_path);
        }

        $this->assertDatabaseCount('project_documents', 2);
    }

    public function test_observer_can_download_existing_document_but_cannot_generate_one(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $observer = User::factory()->create();
        $template = DocumentTemplate::query()->where('type', DocumentType::TasksList->value)->firstOrFail();
        $this->addMember($project, $observer, ProjectRole::Observer);
        Storage::disk('local')->put('generated-documents/test.docx', 'docx');
        Storage::disk('local')->put('generated-documents/test.pdf', 'pdf');
        $document = ProjectDocument::create([
            'project_id' => $project->id,
            'document_template_id' => $template->id,
            'generated_by' => $project->manager_id,
            'type' => DocumentType::TasksList,
            'title' => DocumentType::TasksList->label(),
            'version' => 1,
            'docx_path' => 'generated-documents/test.docx',
            'pdf_path' => 'generated-documents/test.pdf',
            'docx_file_name' => 'lista.docx',
            'pdf_file_name' => 'lista.pdf',
            'generated_at' => now(),
        ]);

        $this->actingAs($observer)
            ->get(route('projects.documents.download', [$project, $document, 'pdf']))
            ->assertOk();

        $this->actingAs($observer)
            ->post(route('projects.documents.generate', $project), [
                'document_template_id' => $template->id,
            ])
            ->assertForbidden();
    }

    public function test_document_from_another_project_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $template = DocumentTemplate::query()->where('type', DocumentType::TasksList->value)->firstOrFail();
        $document = ProjectDocument::create([
            'project_id' => $otherProject->id,
            'document_template_id' => $template->id,
            'generated_by' => $otherProject->manager_id,
            'type' => DocumentType::TasksList,
            'title' => DocumentType::TasksList->label(),
            'version' => 1,
            'docx_path' => 'x.docx',
            'pdf_path' => 'x.pdf',
            'docx_file_name' => 'x.docx',
            'pdf_file_name' => 'x.pdf',
            'generated_at' => now(),
        ]);

        $this->actingAs($administrator)
            ->get(route('projects.documents.download', [$project, $document, 'docx']))
            ->assertNotFound();
    }

    public function test_only_administrator_can_create_document_templates(): void
    {
        $administrator = User::factory()->administrator()->create();
        $ordinaryUser = User::factory()->create();
        $data = [
            'name' => 'Visão Executiva',
            'description' => 'Versão executiva do Documento de Visão.',
            'type' => DocumentType::Vision->value,
            'version' => 2,
            'header_text' => 'SGP Executivo',
            'footer_text' => 'Uso institucional',
            'is_active' => 1,
        ];

        $this->actingAs($ordinaryUser)
            ->post(route('document-templates.store'), $data)
            ->assertForbidden();

        $this->actingAs($administrator)
            ->post(route('document-templates.store'), $data)
            ->assertRedirect(route('document-templates.index'));

        $this->assertDatabaseHas('document_templates', [
            'name' => 'Visão Executiva',
            'type' => DocumentType::Vision->value,
            'version' => 2,
        ]);
    }

    /** @return array<string, string> */
    private function visionData(): array
    {
        return [
            'document_context' => 'A equipe utiliza fontes separadas para acompanhar o projeto.',
            'problem_statement' => 'A fragmentação prejudica a rastreabilidade e a atualização dos artefatos.',
            'solution_summary' => 'Centralizar dados e gerar documentos automaticamente.',
            'target_audience' => "Analistas de requisitos\nGerentes de projetos",
            'scope_included' => "Gestão de projetos\nGeração de documentos",
            'scope_excluded' => 'Integrações externas nesta versão.',
            'assumptions' => 'Disponibilidade dos responsáveis para validação.',
            'constraints' => 'Uso das tecnologias homologadas.',
            'success_criteria' => 'Documentos gerados e homologados.',
            'future_vision' => 'Ampliar a automação e a rastreabilidade.',
        ];
    }

    private function addMember(Project $project, User $user, ProjectRole $role): void
    {
        ProjectMembership::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
            'started_at' => today(),
        ]);
    }
}
