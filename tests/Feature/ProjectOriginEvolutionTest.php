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
use App\Services\ProjectOriginEvolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectOriginEvolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('organizations');
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_summary_distinguishes_updated_added_and_unchanged_documents(): void
    {
        [$actor, $project] = $this->scenario();
        Storage::fake('local');
        $documents = app(ProjectOriginDocumentService::class);
        $contract = $documents->store($project, $actor, UploadedFile::fake()->createWithContent('contrato.pdf', 'contrato-v1'), $this->data('Contrato'));
        $charter = $documents->store($project, $actor, UploadedFile::fake()->createWithContent('tap.pdf', 'tap-v1'), $this->data('TAP'));
        $documents->establishInitialBaseline($project, $actor, [$contract->id, $charter->id], 'Acervo recebido.');

        $documents->store($project, $actor, UploadedFile::fake()->createWithContent('contrato-v2.pdf', 'contrato-v2'), $this->data('Contrato', $contract->id));
        $documents->store($project, $actor, UploadedFile::fake()->createWithContent('plano.pdf', 'plano'), $this->data('Plano de trabalho'));
        $summary = app(ProjectOriginEvolutionService::class)->summarize($project);

        $this->assertSame(['updated' => 1, 'added' => 1, 'unchanged' => 1], $summary['counts']);
        $this->assertSame(['added', 'unchanged', 'updated'], $summary['entries']->pluck('status')->sort()->values()->all());
    }

    public function test_origin_screen_explains_evolution_after_initial_reference(): void
    {
        [$actor, $project, $organization] = $this->scenario(true);
        Storage::fake('local');
        $documents = app(ProjectOriginDocumentService::class);
        $document = $documents->store($project, $actor, UploadedFile::fake()->createWithContent('visao.pdf', 'visao'), $this->data('Documento de Visão'));
        $documents->establishInitialBaseline($project, $actor, [$document->id], null);

        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->get(route('projects.origin-documents.index', $project))
            ->assertOk()->assertSee('O que mudou desde a incorporação')->assertSee('Sem alteração');
    }

    /** @return array{0: User, 1: Project, 2?: Organization} */
    private function scenario(bool $withOrganization = false): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'role_code' => OrganizationRole::Administrator, 'status' => OrganizationMembershipStatus::Active]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
        $project = Project::factory()->create(['manager_id' => $actor->id, 'origin_type' => ProjectOriginType::Incorporated]);

        return $withOrganization ? [$actor, $project, $organization] : [$actor, $project];
    }

    /** @return array<string, mixed> */
    private function data(string $title, ?int $replaces = null): array
    {
        return ['origin_category' => 'other', 'origin_title' => $title, 'external_reference' => null, 'original_document_date' => null, 'declared_version' => null, 'description' => null, 'replaces_attachment_id' => $replaces];
    }
}
