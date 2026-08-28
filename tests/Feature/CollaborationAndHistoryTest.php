<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectComment;
use App\Models\ProjectMembership;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollaborationAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_register_and_consult_contextual_comment(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create(['name' => 'Participante Comentadora']);
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);
        $this->addMember($project, $member, ProjectRole::Developer);

        $this->actingAs($member)
            ->post(route('projects.comments.store', $project), [
                'context' => 'requirement:'.$requirement->id,
                'body' => 'Validar este requisito com a unidade demandante.',
            ])
            ->assertRedirect(route('projects.comments.index', $project));

        $this->assertDatabaseHas('project_comments', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'context_type' => 'requirement',
            'context_id' => $requirement->id,
        ]);

        $this->actingAs($member)
            ->get(route('projects.comments.index', $project))
            ->assertOk()
            ->assertSee('Validar este requisito com a unidade demandante.')
            ->assertSee($requirement->code);
    }

    public function test_user_outside_project_cannot_access_comments_or_attachments(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('projects.comments.index', $project))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('projects.attachments.index', $project))
            ->assertForbidden();
    }

    public function test_context_from_another_project_is_rejected(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $foreignTask = Task::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($administrator)
            ->post(route('projects.comments.store', $project), [
                'context' => 'task:'.$foreignTask->id,
                'body' => 'Comentário indevido.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('project_comments', 0);
    }

    public function test_member_can_upload_and_download_private_attachment(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $this->addMember($project, $member, ProjectRole::Developer);

        $this->actingAs($member)
            ->post(route('projects.attachments.store', $project), [
                'context' => 'task:'.$task->id,
                'description' => 'Evidência da implementação.',
                'file' => UploadedFile::fake()->create('evidencia.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('projects.attachments.index', $project));

        $attachment = ProjectAttachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('task', $attachment->context_type);
        $this->assertSame('evidencia.pdf', $attachment->original_name);

        $this->actingAs($member)
            ->get(route('projects.attachments.download', [$project, $attachment]))
            ->assertOk();
    }

    public function test_disallowed_attachment_type_is_rejected(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('projects.attachments.store', $project), [
                'context' => 'project:'.$project->id,
                'file' => UploadedFile::fake()->create('programa.exe', 20, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('project_attachments', 0);
    }

    public function test_only_uploader_manager_or_administrator_can_remove_attachment(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $uploader = User::factory()->create();
        $observer = User::factory()->create();
        $this->addMember($project, $uploader, ProjectRole::Developer);
        $this->addMember($project, $observer, ProjectRole::Observer);
        Storage::disk('local')->put('project-attachments/teste.pdf', 'arquivo');

        $attachment = ProjectAttachment::create([
            'project_id' => $project->id,
            'uploaded_by' => $uploader->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'disk' => 'local',
            'path' => 'project-attachments/teste.pdf',
            'original_name' => 'teste.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 7,
        ]);

        $this->actingAs($observer)
            ->delete(route('projects.attachments.destroy', [$project, $attachment]))
            ->assertForbidden();

        $this->actingAs($uploader)
            ->delete(route('projects.attachments.destroy', [$project, $attachment]))
            ->assertRedirect(route('projects.attachments.index', $project));

        $this->assertSoftDeleted('project_attachments', ['id' => $attachment->id]);
        $this->assertSame(
            $uploader->id,
            ProjectAttachment::withTrashed()->findOrFail($attachment->id)->deleted_by,
        );
        Storage::disk('local')->assertExists('project-attachments/teste.pdf');
    }

    public function test_attachment_from_another_project_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        Storage::disk('local')->put('project-attachments/outro.pdf', 'arquivo');
        $attachment = ProjectAttachment::create([
            'project_id' => $otherProject->id,
            'uploaded_by' => $administrator->id,
            'context_type' => 'project',
            'context_id' => $otherProject->id,
            'disk' => 'local',
            'path' => 'project-attachments/outro.pdf',
            'original_name' => 'outro.pdf',
            'size_bytes' => 7,
        ]);

        $this->actingAs($administrator)
            ->get(route('projects.attachments.download', [$project, $attachment]))
            ->assertNotFound();
    }

    public function test_project_manager_can_remove_attachment_sent_by_another_member(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $manager = User::factory()->create();
        $uploader = User::factory()->create();
        $this->addMember($project, $manager, ProjectRole::ProjectManager);
        $this->addMember($project, $uploader, ProjectRole::Developer);
        Storage::disk('local')->put('project-attachments/gerencial.pdf', 'arquivo');
        $attachment = ProjectAttachment::create([
            'project_id' => $project->id,
            'uploaded_by' => $uploader->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'disk' => 'local',
            'path' => 'project-attachments/gerencial.pdf',
            'original_name' => 'gerencial.pdf',
            'size_bytes' => 7,
        ]);

        $this->actingAs($manager)
            ->delete(route('projects.attachments.destroy', [$project, $attachment]))
            ->assertRedirect(route('projects.attachments.index', $project));

        $this->assertSoftDeleted('project_attachments', ['id' => $attachment->id]);
    }

    public function test_history_consolidates_requirements_tasks_comments_and_attachments(): void
    {
        $project = Project::factory()->create();
        $administrator = User::factory()->administrator()->create(['name' => 'Liliane Auditora']);
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'code' => 'REQ-900',
        ]);
        RequirementVersion::create([
            'requirement_id' => $requirement->id,
            'version_number' => 1,
            'title' => $requirement->title,
            'description' => $requirement->description,
            'acceptance_criteria' => $requirement->acceptance_criteria,
            'changed_by' => $administrator->id,
            'change_reason' => 'Ajuste homologado.',
            'created_at' => now(),
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'code' => 'TAR-900',
        ]);
        $task->histories()->create([
            'changed_by' => $administrator->id,
            'event' => 'created',
            'to_status' => $task->status->value,
            'created_at' => now(),
        ]);
        ProjectComment::create([
            'project_id' => $project->id,
            'user_id' => $administrator->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'body' => 'Decisão registrada para homologação.',
        ]);
        ProjectAttachment::create([
            'project_id' => $project->id,
            'uploaded_by' => $administrator->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'disk' => 'local',
            'path' => 'project-attachments/ata.pdf',
            'original_name' => 'ata.pdf',
            'size_bytes' => 100,
        ]);

        $this->actingAs($administrator)
            ->get(route('projects.history.index', $project))
            ->assertOk()
            ->assertSee('Ajuste homologado.')
            ->assertSee('Tarefa cadastrada')
            ->assertSee('Decisão registrada para homologação.')
            ->assertSee('ata.pdf')
            ->assertSee('Liliane Auditora');
    }

    public function test_history_filter_limits_results_to_selected_category(): void
    {
        $project = Project::factory()->create();
        $administrator = User::factory()->administrator()->create();
        ProjectComment::create([
            'project_id' => $project->id,
            'user_id' => $administrator->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'body' => 'Comentário exclusivo do filtro.',
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Tarefa que não deve aparecer',
        ]);

        $this->actingAs($administrator)
            ->get(route('projects.history.index', [$project, 'type' => 'comment']))
            ->assertOk()
            ->assertSee('Comentário exclusivo do filtro.')
            ->assertDontSee('Tarefa que não deve aparecer');
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
