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
use App\Services\CommercialJourneyService;
use App\Services\InitiativeConfigurationService;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialJourneyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_journey_accepts_the_exact_proposal_version(): void
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

        $initiative = app(InitiativeConfigurationService::class)->create([
            'title' => 'Iniciativa comercial',
            'origin' => InitiativeOrigin::Commercial,
            'execution_nature' => ExecutionNature::Internal,
            'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential,
            'methodology' => ProjectMethodology::Kanban,
        ], $actor, 'Configuração inicial');

        $journey = app(CommercialJourneyService::class);
        $opportunity = $journey->createOpportunity($initiative, [
            'title' => 'Oportunidade',
            'priority' => 'normal',
        ], $actor);
        $proposal = $journey->proposal($opportunity, ['scope_summary' => 'Versão aceita'], $actor);
        $version = $proposal->versions()->firstOrFail();

        $this->actingAs($actor)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('commercial.negotiations.store', $opportunity), [
                'interaction_type' => 'acceptance',
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'summary' => 'Aceite formal',
                'proposal_version_id' => $version->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('negotiation_entries', [
            'opportunity_id' => $opportunity->id,
            'proposal_id' => $proposal->id,
            'proposal_version_id' => $version->id,
            'interaction_type' => 'acceptance',
            'decision' => 'Aceita',
        ]);
    }

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }
}
