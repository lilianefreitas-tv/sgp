<?php

namespace Tests\Feature;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\InitiativeState;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectMethodology;
use App\Models\Initiative;
use App\Models\InitiativeConfigurationVersion;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\ProjectConfigurationVersion;
use App\Models\User;
use App\Services\InitiativeConfigurationService;
use App\Services\OrganizationContext;
use App\Services\ProjectConfigurationService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class InitiativeFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_service_creates_initiative_with_five_dimensions_and_initial_history(): void
    {
        [$organization, $actor] = $this->activateMembership();
        $initiative = app(InitiativeConfigurationService::class)->create($this->attributes(), $actor, 'Registro inicial aprovado.');

        $this->assertSame($organization->id, $initiative->organization_id);
        $this->assertSame('INI-000001', $initiative->code);
        $this->assertSame(InitiativeState::Draft, $initiative->state);
        $this->assertSame(InitiativeOrigin::Commercial, $initiative->origin);
        $this->assertSame(ManagementLevel::Essential, $initiative->management_level);
        $this->assertSame(ProjectMethodology::Kanban, $initiative->methodology);
        $this->assertDatabaseHas('initiative_configuration_versions', [
            'initiative_id' => $initiative->id, 'sequence' => 1, 'changed_by' => $actor->id,
            'justification' => 'Registro inicial aprovado.', 'superseded_at' => null,
        ]);
    }

    public function test_initiative_sequence_is_independent_per_organization(): void
    {
        [, $actorA] = $this->activateMembership();
        $service = app(InitiativeConfigurationService::class);
        $firstA = $service->create($this->attributes(), $actorA, 'Inicial');
        $secondA = $service->create($this->attributes(), $actorA, 'Inicial');
        app(OrganizationContext::class)->clear();
        [, $actorB] = $this->activateMembership();
        $firstB = $service->create($this->attributes(), $actorB, 'Inicial');

        $this->assertSame('INI-000001', $firstA->code);
        $this->assertSame('INI-000002', $secondA->code);
        $this->assertSame('INI-000001', $firstB->code);
    }

    public function test_dimension_change_is_prospective_and_preserves_previous_version(): void
    {
        [, $actor] = $this->activateMembership();
        $service = app(InitiativeConfigurationService::class);
        $initiative = $service->create($this->attributes(), $actor, 'Inicial');
        $version = $service->change($initiative, ['management_level' => ManagementLevel::Complete], $actor, 'Maior governança necessária.');

        $this->assertSame(2, $version->sequence);
        $this->assertSame(ManagementLevel::Complete, $initiative->fresh()->management_level);
        $this->assertNotNull($initiative->configurationVersions()->where('sequence', 1)->firstOrFail()->superseded_at);
        $this->assertNull($version->superseded_at);
        $this->assertGreaterThanOrEqual($version->effective_from, $version->recorded_at);
    }

    public function test_justification_is_required(): void
    {
        [, $actor] = $this->activateMembership();
        $this->expectException(LogicException::class);
        app(InitiativeConfigurationService::class)->create($this->attributes(), $actor, ' ');
    }

    public function test_historical_version_cannot_be_edited_or_deleted(): void
    {
        [, $actor] = $this->activateMembership();
        $initiative = app(InitiativeConfigurationService::class)->create($this->attributes(), $actor, 'Inicial');
        $version = $initiative->configurationVersions()->firstOrFail();
        try {
            $version->update(['justification' => 'Alterada']);
            $this->fail('A alteração deveria ser rejeitada.');
        } catch (LogicException) {
            $this->assertSame('Inicial', $version->fresh()->justification);
        }
        $this->expectException(LogicException::class);
        $version->delete();
    }

    public function test_historical_version_only_allows_controlled_prospective_supersession(): void
    {
        [, $actor] = $this->activateMembership();
        $initiative = app(InitiativeConfigurationService::class)->create($this->attributes(), $actor, 'Inicial');
        $version = $initiative->configurationVersions()->firstOrFail();

        $version->justification = 'Tentativa de alteração';
        $version->superseded_at = now();
        try {
            $version->save();
            $this->fail('A atualização mista deveria ser rejeitada.');
        } catch (LogicException) {
            $this->assertNull($version->fresh()->superseded_at);
        }

        $this->expectException(LogicException::class);
        $version->supersede(now()->subDay());
    }

    public function test_historical_version_cannot_clear_or_replace_supersession(): void
    {
        [, $actor] = $this->activateMembership();
        $initiative = app(InitiativeConfigurationService::class)->create($this->attributes(), $actor, 'Inicial');
        app(InitiativeConfigurationService::class)->change($initiative, ['methodology' => ProjectMethodology::Scrum], $actor, 'Mudança válida');
        $version = $initiative->configurationVersions()->where('sequence', 1)->firstOrFail();

        foreach ([null, now()->addDay()] as $supersededAt) {
            try {
                $version->update(['superseded_at' => $supersededAt]);
                $this->fail('Uma versão encerrada não pode ser reaberta nem reencerrada.');
            } catch (LogicException) {
                $this->assertNotNull($version->fresh()->superseded_at);
            }
        }
    }

    public function test_active_context_filters_initiatives_from_other_organizations(): void
    {
        [$organization, , $membership] = $this->activateMembership();
        $visible = Initiative::factory()->create(['organization_id' => $organization->id]);
        app(OrganizationContext::class)->clear();
        $foreign = Initiative::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
        $this->assertTrue(Initiative::query()->whereKey($visible)->exists());
        $this->assertFalse(Initiative::query()->whereKey($foreign)->exists());
    }

    public function test_initiative_factory_can_explicitly_produce_a_coherent_aggregate(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $initiative = Initiative::factory()->forOrganization($organization)->withActor($actor)->withActiveMembership()->create();

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'status' => OrganizationMembershipStatus::Active->value,
        ]);
        $this->assertSame($organization->id, $initiative->organization_id);
        $this->assertSame($actor->id, $initiative->created_by);
    }

    public function test_project_accepts_null_initiative_and_rejects_cross_tenant_initiative(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $initiative = Initiative::factory()->create(['organization_id' => $organizationA->id]);
        $this->assertNull(Project::factory()->create(['organization_id' => $organizationB->id, 'client_id' => null])->initiative_id);
        $this->expectException(QueryException::class);
        Project::factory()->create(['organization_id' => $organizationB->id, 'client_id' => null, 'initiative_id' => $initiative->id]);
    }

    public function test_one_initiative_cannot_be_linked_to_two_projects(): void
    {
        $organization = Organization::factory()->create();
        $initiative = Initiative::factory()->create(['organization_id' => $organization->id]);
        Project::factory()->create(['organization_id' => $organization->id, 'client_id' => null, 'initiative_id' => $initiative->id]);
        $this->expectException(QueryException::class);
        Project::factory()->create(['organization_id' => $organization->id, 'client_id' => null, 'initiative_id' => $initiative->id]);
    }

    public function test_service_rejects_actor_without_active_membership(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id,
            'status' => OrganizationMembershipStatus::Suspended]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
        $this->expectException(LogicException::class);
        app(InitiativeConfigurationService::class)->create($this->attributes(), $user, 'Inicial');
    }

    public function test_superadmin_requires_explicit_platform_access_and_does_not_gain_membership(): void
    {
        $organization = Organization::factory()->create();
        $superadmin = User::factory()->administrator()->create();
        $service = app(InitiativeConfigurationService::class);

        try {
            $service->create($this->attributes(), $superadmin, 'Inicial');
            $this->fail('A criação sem acesso temporário deveria ser rejeitada.');
        } catch (LogicException) {
            $this->assertDatabaseMissing('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $superadmin->id]);
        }

        app(OrganizationContext::class)->activatePlatformAccess($organization, collect());
        $initiative = $service->create($this->attributes(), $superadmin, 'Inicial');
        $this->assertSame($superadmin->id, $initiative->created_by);
        $this->assertDatabaseMissing('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $superadmin->id]);
    }

    public function test_inactive_actor_is_rejected_even_with_active_membership_or_platform_access(): void
    {
        [$organization, $actor] = $this->activateMembership();
        $actor->update(['is_active' => false]);
        $this->expectException(LogicException::class);
        app(InitiativeConfigurationService::class)->create($this->attributes(), $actor->fresh(), 'Inicial');
    }

    public function test_inactive_superadmin_is_rejected_with_platform_access(): void
    {
        $organization = Organization::factory()->create();
        $superadmin = User::factory()->administrator()->create(['is_active' => false]);
        app(OrganizationContext::class)->activatePlatformAccess($organization, collect());

        $this->expectException(LogicException::class);
        app(InitiativeConfigurationService::class)->create($this->attributes(), $superadmin, 'Inicial');
    }

    public function test_initiative_change_rejects_extra_fields_without_partial_write(): void
    {
        [, $actor] = $this->activateMembership();
        $service = app(InitiativeConfigurationService::class);
        $initiative = $service->create($this->attributes(), $actor, 'Inicial');

        try {
            $service->change($initiative, ['management_level' => ManagementLevel::Complete, 'lock_version' => 999], $actor, 'Inválida');
            $this->fail('Campos fora das dimensões devem ser rejeitados.');
        } catch (LogicException) {
            $this->assertSame(ManagementLevel::Essential, $initiative->fresh()->management_level);
            $this->assertSame(0, $initiative->fresh()->lock_version);
            $this->assertSame(1, $initiative->configurationVersions()->count());
        }
    }

    public function test_initiative_cannot_be_hard_deleted(): void
    {
        [, $actor] = $this->activateMembership();
        $initiative = app(InitiativeConfigurationService::class)->create($this->attributes(), $actor, 'Inicial');

        $this->expectException(LogicException::class);
        $initiative->delete();
    }

    public function test_initiative_change_does_not_propagate_to_linked_project(): void
    {
        [$organization, $actor] = $this->activateMembership();
        $service = app(InitiativeConfigurationService::class);
        $initiative = $service->create($this->attributes(), $actor, 'Inicial');
        $project = Project::factory()->create(['organization_id' => $organization->id, 'client_id' => null,
            'initiative_id' => $initiative->id, 'management_level' => ManagementLevel::Essential]);

        $service->change($initiative, ['management_level' => ManagementLevel::Complete], $actor, 'Governança ampliada.');
        $this->assertSame(ManagementLevel::Essential, $project->fresh()->management_level);
    }

    public function test_project_configuration_source_rejects_version_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $initiative = Initiative::factory()->create(['organization_id' => $organizationA->id]);
        $source = InitiativeConfigurationVersion::factory()->create(['organization_id' => $organizationA->id, 'initiative_id' => $initiative->id]);
        $project = Project::factory()->create(['organization_id' => $organizationB->id, 'client_id' => null]);

        $this->expectException(QueryException::class);
        ProjectConfigurationVersion::factory()->create(['organization_id' => $organizationB->id,
            'project_id' => $project->id, 'source_initiative_configuration_version_id' => $source->id]);
    }

    public function test_project_configuration_service_records_and_changes_versions_without_propagation(): void
    {
        [$organization, $actor] = $this->activateMembership();
        $project = Project::factory()->create(['organization_id' => $organization->id, 'manager_id' => $actor->id, 'client_id' => null]);
        $service = app(ProjectConfigurationService::class);
        $initial = $service->recordInitial($project, $actor, 'Configuração inicial');
        $next = $service->change($project, [
            'execution_nature' => ExecutionNature::Mixed,
            'financial_management_mode' => FinancialManagementMode::FixedPrice,
            'management_level' => ManagementLevel::Complete,
            'methodology' => ProjectMethodology::Traditional,
        ], $actor, 'Configuração revisada');

        $this->assertSame(1, $initial->sequence);
        $this->assertSame(2, $next->sequence);
        $this->assertNotNull($initial->fresh()->superseded_at);
        $this->assertSame('Configuração revisada', $next->justification);
        $this->assertSame(ManagementLevel::Complete, $project->fresh()->management_level);
    }

    public function test_project_configuration_service_rejects_extra_fields_and_inactive_actor(): void
    {
        [$organization, $actor] = $this->activateMembership();
        $project = Project::factory()->create(['organization_id' => $organization->id, 'manager_id' => $actor->id, 'client_id' => null]);
        $service = app(ProjectConfigurationService::class);
        $service->recordInitial($project, $actor, 'Inicial');

        try {
            $service->change($project, ['methodology' => ProjectMethodology::Scrum, 'organization_id' => 999], $actor, 'Inválida');
            $this->fail('Campo extra deve ser rejeitado.');
        } catch (LogicException) {
            $this->assertSame(1, $project->configurationVersions()->count());
        }

        $actor->update(['is_active' => false]);
        $this->expectException(LogicException::class);
        $service->change($project, ['methodology' => ProjectMethodology::Scrum], $actor->fresh(), 'Ator inativo');
    }

    public function test_project_configuration_service_allows_only_active_superadmin_with_platform_access(): void
    {
        $organization = Organization::factory()->create();
        $project = Project::factory()->create(['organization_id' => $organization->id, 'client_id' => null]);
        $superadmin = User::factory()->administrator()->create();
        $service = app(ProjectConfigurationService::class);

        try {
            $service->recordInitial($project, $superadmin, 'Sem acesso temporário');
            $this->fail('O superadmin sem acesso temporário deveria ser rejeitado.');
        } catch (LogicException) {
            $this->assertSame(0, $project->configurationVersions()->count());
        }

        app(OrganizationContext::class)->activatePlatformAccess($organization, collect());
        $version = $service->recordInitial($project, $superadmin, 'Acesso temporário autorizado');
        $this->assertSame($superadmin->id, $version->changed_by);
    }

    public function test_incremental_migration_converts_simplified_and_keeps_existing_project_without_initiative(): void
    {
        $originalConnection = DB::getDefaultConnection();
        config()->set('database.connections.initiative_migration_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('initiative_migration_test');
        DB::setDefaultConnection('initiative_migration_test');

        try {
            Schema::create('users', fn (Blueprint $table) => $table->id());
            Schema::create('organizations', fn (Blueprint $table) => $table->id());
            Schema::create('projects', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->string('management_level', 30)->default(ManagementLevel::Simplified->value);
                $table->unique(['id', 'organization_id'], 'projects_id_org_unique');
            });
            DB::table('organizations')->insert(['id' => 1]);
            DB::table('projects')->insert([
                'id' => 1,
                'organization_id' => 1,
                'management_level' => ManagementLevel::Simplified->value,
            ]);

            $migration = require database_path('migrations/2026_08_12_000000_create_initiative_foundation.php');
            $migration->up();

            $row = DB::table('projects')->where('id', 1)->first();
            $this->assertSame(ManagementLevel::Essential->value, $row->management_level);
            $this->assertNull($row->initiative_id);
            $this->assertSame(0, DB::table('initiatives')->count());
        } finally {
            DB::disconnect('initiative_migration_test');
            DB::setDefaultConnection($originalConnection);
        }
    }

    private function activateMembership(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id,
            'role_code' => OrganizationRole::Administrator, 'status' => OrganizationMembershipStatus::Active]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));

        return [$organization, $user, $membership];
    }

    private function attributes(): array
    {
        return ['title' => 'Iniciativa adaptativa', 'context' => 'Contexto inicial', 'origin' => InitiativeOrigin::Commercial,
            'state' => InitiativeState::Draft, 'execution_nature' => ExecutionNature::Mixed,
            'financial_management_mode' => FinancialManagementMode::FixedPrice,
            'management_level' => ManagementLevel::Essential, 'methodology' => ProjectMethodology::Kanban];
    }
}
