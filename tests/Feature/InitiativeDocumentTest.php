<?php

namespace Tests\Feature;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectMethodology;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\ArtifactRevisionService;
use App\Services\InitiativeConfigurationService;
use App\Services\InitiativeDocumentService;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitiativeDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_dossier_is_generated_from_the_initiative_without_manual_duplication(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = app(InitiativeConfigurationService::class)->create([
            'title' => 'Aplicativo móvel de chope',
            'context' => 'Cliente deseja vender chope pelo aplicativo.',
            'origin' => InitiativeOrigin::Commercial,
            'execution_nature' => ExecutionNature::Internal,
            'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential,
            'methodology' => ProjectMethodology::Kanban,
        ], $actor, 'Registro inicial.');

        $response = $this->actingAs($actor)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('initiatives.documents.dossier', $initiative));

        $response->assertRedirect();
        $artifact = $initiative->artifacts()->sole();
        $content = $artifact->revisions()->sole()->content;
        $this->assertSame('Aplicativo móvel de chope', $content['identificacao']['titulo']);
        $this->assertSame('Cliente deseja vender chope pelo aplicativo.', $content['identificacao']['contexto']);
        $this->assertArrayHasKey('jornada_comercial', $content);
        $this->assertSame($organization->id, $artifact->organization_id);
    }

    public function test_dossier_does_not_create_a_duplicate_revision_when_sources_are_unchanged(): void
    {
        [, $actor] = $this->actor();
        $initiative = app(InitiativeConfigurationService::class)->create([
            'title' => 'Iniciativa interna',
            'origin' => InitiativeOrigin::Internal,
            'execution_nature' => ExecutionNature::Internal,
            'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential,
            'methodology' => ProjectMethodology::Kanban,
        ], $actor, 'Registro inicial.');

        $service = app(InitiativeDocumentService::class);
        $first = $service->synchronizeDossier($initiative, $actor);
        $second = $service->synchronizeDossier($initiative, $actor);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $second->revisions()->count());
    }

    public function test_partial_revision_preserves_the_complete_previous_snapshot(): void
    {
        [, $actor] = $this->actor();
        $initiative = app(InitiativeConfigurationService::class)->create([
            'title' => 'Iniciativa interna',
            'origin' => InitiativeOrigin::Internal,
            'execution_nature' => ExecutionNature::Internal,
            'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential,
            'methodology' => ProjectMethodology::Kanban,
        ], $actor, 'Registro inicial.');
        $artifact = app(InitiativeDocumentService::class)->synchronizeDossier($initiative, $actor);

        $revision = app(ArtifactRevisionService::class)->revise(
            $artifact,
            ['observacao_complementar' => 'Registro posterior.'],
            null,
            1,
            'Complemento.',
            $actor,
        );

        $this->assertSame('Iniciativa interna', $revision->content['identificacao']['titulo']);
        $this->assertSame('Registro posterior.', $revision->content['observacao_complementar']);
    }

    /** @return array{0: Organization, 1: User} */
    private function actor(): array
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

        return [$organization, $actor];
    }
}
