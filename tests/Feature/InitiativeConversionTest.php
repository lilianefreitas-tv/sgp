<?php

namespace Tests\Feature;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\GlobalProfile;
use App\Enums\InitiativeOrigin;
use App\Enums\InitiativeState;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectMethodology;
use App\Models\Client;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use App\Services\CommercialJourneyService;
use App\Services\InitiativeConfigurationService;
use App\Services\InitiativeConversionService;
use App\Services\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class InitiativeConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_valid_commercial_conversion_preserves_source_and_creates_initial_history(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->commercialInitiativeWithAcceptance($actor);
        app(InitiativeConfigurationService::class)->change($initiative, [
            'management_level' => ManagementLevel::Complete,
        ], $actor, 'Configuração final para conversão.');
        $source = $initiative->configurationVersions()->whereNull('superseded_at')->firstOrFail();

        $project = $this->convert($initiative, $actor, $organization);

        $this->assertSame($initiative->id, $project->initiative_id);
        $this->assertSame($source->id, $project->source_initiative_configuration_version_id);
        $this->assertSame($initiative->title, $project->name);
        $this->assertSame($initiative->context, $project->description);
        $this->assertSame($source->execution_nature, $project->execution_nature);
        $this->assertSame($source->financial_management_mode, $project->financial_management_mode);
        $this->assertSame($source->management_level, $project->management_level);
        $this->assertSame($source->methodology, $project->methodology);
        $this->assertSame(InitiativeState::Converted, $initiative->fresh()->state);
        $this->assertSame($actor->id, $initiative->fresh()->converted_by);
        $this->assertNotNull($initiative->fresh()->converted_at);
        $this->assertDatabaseHas('project_configuration_versions', [
            'project_id' => $project->id,
            'sequence' => 1,
            'source_initiative_configuration_version_id' => $source->id,
        ]);
        $this->assertDatabaseHas('project_activities', ['project_id' => $project->id, 'event_type' => 'initiative_converted']);
    }

    public function test_internal_direct_and_existing_contract_convert_without_commercial_records(): void
    {
        [$organization, $actor] = $this->actor();
        foreach ([InitiativeOrigin::Internal, InitiativeOrigin::Direct, InitiativeOrigin::ExistingContract] as $origin) {
            $initiative = $this->initiative($actor, $origin);
            $project = $this->convert($initiative, $actor, $organization);
            $this->assertSame($initiative->id, $project->initiative_id);
            $this->assertDatabaseMissing('opportunities', ['initiative_id' => $initiative->id]);
            $this->assertDatabaseMissing('proposals', ['initiative_id' => $initiative->id]);
            $this->assertDatabaseMissing('negotiation_entries', ['initiative_id' => $initiative->id]);
        }
    }

    public function test_conversion_is_idempotent_and_never_creates_a_second_project(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor, InitiativeOrigin::Internal);

        $first = $this->convert($initiative, $actor, $organization);
        $second = $this->convert($initiative, $actor, $organization);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Project::query()->where('initiative_id', $initiative->id)->count());
    }

    public function test_lost_rejected_and_expired_commercial_paths_are_blocked(): void
    {
        [$organization, $actor] = $this->actor();
        $service = app(CommercialJourneyService::class);
        $lost = $this->initiative($actor, InitiativeOrigin::Commercial);
        $opportunity = $service->createOpportunity($lost, ['title' => 'Perdida', 'priority' => 'normal'], $actor);
        $service->transition($opportunity, 'lost', $actor, 'Sem aderência');
        $this->expectException(LogicException::class);
        $this->convert($lost, $actor, $organization);
    }

    public function test_rejected_or_expired_accepted_proposal_is_blocked(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->commercialInitiativeWithAcceptance($actor);
        $proposal = $initiative->opportunity->proposals()->firstOrFail();
        $proposal->update(['state' => 'rejected']);

        $this->assertThrows(fn () => $this->convert($initiative, $actor, $organization), LogicException::class);

        $expired = $this->commercialInitiativeWithAcceptance($actor);
        $acceptedVersion = $expired->opportunity->proposals()->firstOrFail()->versions()->firstOrFail();
        $acceptedVersion->forceFill(['validity_until' => today()->subDay()])->saveQuietly();

        $this->assertThrows(fn () => $this->convert($expired, $actor, $organization), LogicException::class);
    }

    public function test_later_initiative_changes_do_not_propagate_to_converted_project(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor, InitiativeOrigin::Direct);
        $project = $this->convert($initiative, $actor, $organization);

        app(InitiativeConfigurationService::class)->change($initiative, [
            'methodology' => ProjectMethodology::Scrum,
        ], $actor, 'Mudança posterior.');

        $this->assertSame(ProjectMethodology::Kanban, $project->fresh()->methodology);
    }

    public function test_legacy_projects_remain_without_initiative(): void
    {
        [$organization, $actor] = $this->actor();
        $legacy = Project::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => Client::factory()->create(['organization_id' => $organization->id])->id,
            'manager_id' => $actor->id,
        ]);

        $this->assertNull($legacy->initiative_id);
        $this->assertNull($legacy->source_initiative_configuration_version_id);
    }

    public function test_cross_tenant_conversion_and_composite_fk_are_rejected(): void
    {
        [$organizationA, $actorA] = $this->actor();
        $initiative = $this->initiative($actorA, InitiativeOrigin::Internal);
        [$organizationB, $actorB] = $this->actor();

        try {
            $this->convert($initiative, $actorB, $organizationB);
            $this->fail('Conversão cross-tenant não deveria ser permitida.');
        } catch (LogicException) {
            $this->assertSame(0, Project::query()->where('initiative_id', $initiative->id)->count());
        }

        $client = Client::factory()->create(['organization_id' => $organizationB->id]);
        $source = $initiative->configurationVersions()->withoutGlobalScopes()->firstOrFail();
        $this->expectException(QueryException::class);
        DB::table('projects')->insert([
            'organization_id' => $organizationB->id,
            'client_id' => $client->id,
            'manager_id' => $actorB->id,
            'initiative_id' => $initiative->id,
            'source_initiative_configuration_version_id' => $source->id,
            'name' => 'Inválido',
            'objective' => 'Inválido',
            'execution_nature' => ExecutionNature::Internal->value,
            'financial_management_mode' => FinancialManagementMode::NotApplicable->value,
            'management_level' => ManagementLevel::Essential->value,
            'methodology' => ProjectMethodology::Kanban->value,
            'status' => 'planning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_inactive_suspended_and_superadmin_without_platform_access_are_rejected(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor, InitiativeOrigin::Internal);
        $actor->update(['is_active' => false]);
        $this->assertThrows(fn () => $this->convert($initiative, $actor->fresh(), $organization), LogicException::class);

        [$organization2, $suspended] = $this->actor();
        $other = $this->initiative($suspended, InitiativeOrigin::Internal, $organization2);
        OrganizationMembership::query()->where('organization_id', $organization2->id)->where('user_id', $suspended->id)
            ->update(['status' => OrganizationMembershipStatus::Suspended]);
        $this->assertThrows(fn () => $this->convert($other, $suspended, $organization2), LogicException::class);

        $superadmin = User::factory()->create(['global_profile' => GlobalProfile::Administrator]);
        app(OrganizationContext::class)->clear();
        $this->assertThrows(fn () => app(InitiativeConversionService::class)->convert($initiative, [
            'client_id' => Client::factory()->create(['organization_id' => $organization->id])->id,
            'objective' => 'Objetivo operacional.',
        ], $superadmin), LogicException::class);
    }

    public function test_superadmin_with_explicit_platform_access_can_convert(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor, InitiativeOrigin::Internal);
        $superadmin = User::factory()->create(['global_profile' => GlobalProfile::Administrator]);
        app(OrganizationContext::class)->activatePlatformAccess($organization, collect());

        $project = app(InitiativeConversionService::class)->convert($initiative, [
            'client_id' => Client::factory()->create(['organization_id' => $organization->id])->id,
            'objective' => 'Objetivo operacional.',
        ], $superadmin);

        $this->assertSame($initiative->id, $project->initiative_id);
        $this->assertSame($actor->id, $project->manager_id);
    }

    public function test_unavailable_action_is_hidden_in_the_page_and_blocked_on_post(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor, InitiativeOrigin::Commercial);
        $service = app(CommercialJourneyService::class);
        $opportunity = $service->createOpportunity($initiative, ['title' => 'Perdida', 'priority' => 'normal'], $actor);
        $service->transition($opportunity, 'lost', $actor, 'Sem aderência');

        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->get(route('initiatives.conversion.show', $initiative))
            ->assertOk()
            ->assertDontSee('Converter em projeto');
        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->post(route('initiatives.conversion.convert', $initiative), [
                'client_id' => Client::factory()->create(['organization_id' => $organization->id])->id,
                'objective' => 'Objetivo',
            ])->assertStatus(409);
    }

    private function convert(Initiative $initiative, User $actor, Organization $organization): Project
    {
        app(OrganizationContext::class)->activate(
            OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $actor->id)->first()
                ?? OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'role_code' => OrganizationRole::Administrator]),
            OrganizationMembership::query()->where('organization_id', $organization->id)->get(),
        );

        return app(InitiativeConversionService::class)->convert($initiative, [
            'client_id' => Client::factory()->create(['organization_id' => $organization->id])->id,
            'objective' => 'Objetivo operacional da conversão.',
        ], $actor);
    }

    private function commercialInitiativeWithAcceptance(User $actor): Initiative
    {
        $initiative = $this->initiative($actor, InitiativeOrigin::Commercial);
        $commercial = app(CommercialJourneyService::class);
        $opportunity = $commercial->createOpportunity($initiative, ['title' => 'Oportunidade', 'priority' => 'normal'], $actor);
        $proposal = $commercial->proposal($opportunity, ['scope_summary' => 'Escopo'], $actor);
        $version = $proposal->versions()->firstOrFail();
        $commercial->negotiation($opportunity, [
            'interaction_type' => 'acceptance',
            'occurred_at' => now(),
            'proposal_id' => $proposal->id,
            'proposal_version_id' => $version->id,
            'decision' => 'Aceita',
        ], $actor);
        $commercial->transition($opportunity, 'won', $actor, 'Aceite formalizado');

        return $initiative->fresh();
    }

    private function actor(OrganizationMembershipStatus $status = OrganizationMembershipStatus::Active): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_code' => OrganizationRole::Administrator,
            'status' => $status,
        ]);
        if ($status === OrganizationMembershipStatus::Active) {
            app(OrganizationContext::class)->activate($membership, collect([$membership]));
        }

        return [$organization, $user];
    }

    private function initiative(User $actor, InitiativeOrigin $origin, ?Organization $organization = null): Initiative
    {
        if ($organization !== null) {
            $membership = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $actor->id)->firstOrFail();
            app(OrganizationContext::class)->activate($membership, collect([$membership]));
        }

        return app(InitiativeConfigurationService::class)->create([
            'title' => 'Iniciativa de conversão',
            'context' => 'Contexto preservado.',
            'origin' => $origin,
            'execution_nature' => ExecutionNature::Mixed,
            'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential,
            'methodology' => ProjectMethodology::Kanban,
        ], $actor, 'Registro inicial.');
    }
}
