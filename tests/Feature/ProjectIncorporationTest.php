<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectOriginType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ProjectOriginDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class ProjectIncorporationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('organizations');
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_incorporated_project_exposes_origin_documentation_entry(): void
    {
        [$organization, $actor, $project] = $this->scenario();

        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->get(route('projects.origin-documents.index', $project))
            ->assertOk()
            ->assertSee('Documentação de origem')
            ->assertSee('O ponto de partida documental do projeto');

        $this->assertSame(ProjectOriginType::Incorporated, $project->origin_type);
    }

    public function test_origin_document_versions_preserve_file_and_integrity_history(): void
    {
        [, $actor, $project] = $this->scenario();
        Storage::fake('local');
        $service = app(ProjectOriginDocumentService::class);

        $first = $service->store($project, $actor, UploadedFile::fake()->createWithContent('contrato.pdf', 'versao um'), $this->data());
        $second = $service->store($project, $actor, UploadedFile::fake()->createWithContent('contrato-v2.pdf', 'versao dois'), $this->data(['replaces_attachment_id' => $first->id, 'declared_version' => '2']));

        $this->assertSame('historical', $first->fresh()->origin_status);
        $this->assertSame('current', $second->origin_status);
        $this->assertSame($first->origin_series_uuid, $second->origin_series_uuid);
        $this->assertSame(2, $second->origin_version);
        $this->assertNotSame($first->sha256, $second->sha256);
        Storage::disk('local')->assertExists($first->path);
        Storage::disk('local')->assertExists($second->path);
    }

    public function test_initial_reference_accepts_only_current_versions_and_is_single(): void
    {
        [, $actor, $project] = $this->scenario();
        Storage::fake('local');
        $service = app(ProjectOriginDocumentService::class);
        $document = $service->store($project, $actor, UploadedFile::fake()->createWithContent('tap.pdf', 'conteudo'), $this->data(['origin_category' => 'project_charter', 'origin_title' => 'TAP recebido']));

        $baseline = $service->establishInitialBaseline($project, $actor, [$document->id], 'Acervo recebido na incorporação.');

        $this->assertSame($project->code.'-ORIGEM-01', $baseline->code);
        $this->assertSame([$document->id], $baseline->documents()->pluck('project_attachments.id')->all());
        $this->expectException(LogicException::class);
        $service->establishInitialBaseline($project, $actor, [$document->id], null);
    }

    /** @return array{0: Organization, 1: User, 2: Project} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'role_code' => OrganizationRole::Administrator,
            'status' => OrganizationMembershipStatus::Active,
        ]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
        $project = Project::factory()->create(['manager_id' => $actor->id, 'origin_type' => ProjectOriginType::Incorporated]);

        return [$organization, $actor, $project];
    }

    /** @param array<string, mixed> $overrides */
    private function data(array $overrides = []): array
    {
        return array_merge([
            'origin_category' => 'contract',
            'origin_title' => 'Contrato recebido',
            'external_reference' => 'CTR-2026-01',
            'original_document_date' => '2026-08-01',
            'declared_version' => '1',
            'description' => 'Documento anterior ao acompanhamento pelo SGP.',
            'replaces_attachment_id' => null,
        ], $overrides);
    }
}
