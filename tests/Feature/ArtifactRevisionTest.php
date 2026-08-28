<?php

namespace Tests\Feature;

use App\Enums\ArtifactType;
use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\GlobalProfile;
use App\Enums\InitiativeOrigin;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectMethodology;
use App\Models\Artifact;
use App\Models\ArtifactRevision;
use App\Models\Client;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use App\Services\ArtifactRevisionService;
use App\Services\ArtifactSnapshotCanonicalizer;
use App\Services\InitiativeConfigurationService;
use App\Services\InitiativeConversionService;
use App\Services\OrganizationContext;
use App\Services\ProjectConfigurationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class ArtifactRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_creates_initiative_artifact_with_initial_immutable_revision_and_source_snapshot(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor);
        $artifact = $this->service()->create($this->attributes($initiative), $actor);
        $revision = $artifact->revisions->sole();

        $this->assertSame($organization->id, $artifact->organization_id);
        $this->assertSame('ART-000001', $artifact->code);
        $this->assertSame(1, $artifact->current_revision_sequence);
        $this->assertSame($initiative->id, $artifact->initiative_id);
        $this->assertNull($artifact->project_id);
        $this->assertSame($initiative->configurationVersions()->whereNull('superseded_at')->value('id'), $revision->source_initiative_configuration_version_id);
        $this->assertNull($revision->source_project_configuration_version_id);
        $this->assertSame(['a' => 1, 'z' => ['first' => true, 'second' => false]], $revision->content);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $revision->checksum);
    }

    public function test_project_artifact_uses_exact_project_configuration_and_later_parent_changes_do_not_propagate(): void
    {
        [$organization, $actor] = $this->actor();
        $project = $this->project($actor, $organization);
        $artifact = $this->service()->create($this->attributes($project), $actor);
        $first = $artifact->revisions->sole();

        app(ProjectConfigurationService::class)->change($project, ['methodology' => ProjectMethodology::Scrum], $actor, 'Mudança posterior do projeto.');
        $second = $this->service()->revise($artifact, ['z' => ['second' => false, 'first' => true], 'a' => 2], null, 1, 'Revisão posterior.', $actor);

        $this->assertSame($project->configurationVersions()->where('sequence', 1)->value('id'), $first->source_project_configuration_version_id);
        $this->assertNotSame($first->source_project_configuration_version_id, $second->source_project_configuration_version_id);
        $this->assertSame(1, $first->sequence);
        $this->assertSame(2, $second->sequence);
        $this->assertSame(['a' => 1, 'z' => ['first' => true, 'second' => false]], $first->fresh()->content);
    }

    public function test_revisions_are_append_only_and_checksum_is_canonical(): void
    {
        [, $actor] = $this->actor();
        $artifact = $this->service()->create($this->attributes($this->initiative($actor)), $actor);
        $first = $artifact->revisions->sole();
        $second = $this->service()->revise($artifact, ['z' => ['second' => false, 'first' => true], 'a' => 1], ['b' => 2, 'a' => 1], 1, 'Metadados ordenados.', $actor);

        $this->assertNotSame($first->checksum, $second->checksum, 'A sequência integra o envelope do checksum.');
        $this->assertSame(['a' => 1, 'z' => ['first' => true, 'second' => false]], $second->content);
        $this->assertSame($second->checksum, app(ArtifactSnapshotCanonicalizer::class)->checksum([
            'artifact_id' => $artifact->id, 'sequence' => 2, 'schema_version' => 1,
            'content' => ['a' => 1, 'z' => ['first' => true, 'second' => false]],
            'metadata' => ['a' => 1, 'b' => 2],
            'source_initiative_configuration_version_id' => $second->source_initiative_configuration_version_id,
            'source_project_configuration_version_id' => null,
        ]));
        $this->assertThrows(fn () => $first->update(['change_reason' => 'Tentativa']), LogicException::class);
        $this->assertThrows(fn () => $first->updateQuietly(['change_reason' => 'Tentativa']), LogicException::class);
        $this->assertThrows(fn () => $first->delete(), LogicException::class);
        $this->assertThrows(fn () => $first->forceDelete(), LogicException::class);
        $this->assertSame('Registro inicial.', $first->fresh()->change_reason);
    }

    public function test_archived_artifact_rejects_new_revision(): void
    {
        [, $actor] = $this->actor();
        $artifact = $this->service()->create($this->attributes($this->initiative($actor)), $actor);
        $this->service()->archive($artifact, 'Encerrado.', $actor);

        $this->assertNotNull($artifact->fresh()->archived_at);
        $this->assertThrows(fn () => $this->service()->revise($artifact, ['a' => 2], null, 1, 'Não permitido.', $actor), LogicException::class);
        $this->assertThrows(fn () => $artifact->delete(), LogicException::class);
    }

    public function test_service_rejects_zero_or_multiple_parents_and_injected_incompatible_sources(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor);
        $project = $this->project($actor, $organization);
        $noParent = $this->attributes($initiative);
        unset($noParent['initiative_id']);

        $this->assertThrows(fn () => $this->service()->create($noParent, $actor), LogicException::class);
        $this->assertThrows(fn () => $this->service()->create($this->attributes($initiative) + ['project_id' => $project->id], $actor), LogicException::class);
        $this->assertThrows(fn () => $this->service()->create($this->attributes($project) + ['source_initiative_configuration_version_id' => $initiative->configurationVersions()->value('id')], $actor), LogicException::class);
        $this->assertThrows(fn () => $this->service()->create($this->attributes($initiative) + ['source_project_configuration_version_id' => $project->configurationVersions()->value('id')], $actor), LogicException::class);
    }

    public function test_database_rejects_adding_a_second_parent_on_update(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor);
        $project = $this->project($actor, $organization);
        $artifact = $this->service()->create($this->attributes($initiative), $actor);

        $this->expectException(QueryException::class);
        DB::table('artifacts')->where('id', $artifact->id)->update(['project_id' => $project->id]);
    }

    public function test_database_rejects_removing_the_only_parent_on_update(): void
    {
        [, $actor] = $this->actor();
        $artifact = $this->service()->create($this->attributes($this->initiative($actor)), $actor);

        $this->expectException(QueryException::class);
        DB::table('artifacts')->where('id', $artifact->id)->update(['initiative_id' => null]);
    }

    public function test_checksum_canonicalization_preserves_lists_and_distinguishes_all_envelope_fields(): void
    {
        $canonicalizer = app(ArtifactSnapshotCanonicalizer::class);
        $base = ['artifact_id' => 1, 'sequence' => 1, 'schema_version' => 1, 'content' => ['nested' => ['a' => 1, 'b' => null], 'list' => ['first', 1, true]], 'metadata' => ['a' => null], 'source_initiative_configuration_version_id' => 10, 'source_project_configuration_version_id' => null];
        $reordered = ['source_project_configuration_version_id' => null, 'metadata' => ['a' => null], 'content' => ['list' => ['first', 1, true], 'nested' => ['b' => null, 'a' => 1]], 'schema_version' => 1, 'sequence' => 1, 'artifact_id' => 1, 'source_initiative_configuration_version_id' => 10];

        $this->assertSame($canonicalizer->checksum($base), $canonicalizer->checksum($reordered));
        foreach ([
            [...$base, 'content' => ['nested' => ['a' => 1, 'b' => null], 'list' => [true, 1, 'first']]],
            [...$base, 'content' => ['value' => '1']],
            [...$base, 'content' => ['value' => 1]],
            [...$base, 'content' => ['value' => true]],
            [...$base, 'metadata' => ['a' => 'changed']],
            [...$base, 'schema_version' => 2],
            [...$base, 'sequence' => 2],
            [...$base, 'source_initiative_configuration_version_id' => 11],
        ] as $different) {
            $this->assertNotSame($canonicalizer->checksum($base), $canonicalizer->checksum($different));
        }
    }

    public function test_revisions_cannot_be_replicated_or_created_directly_and_reject_non_serializable_content(): void
    {
        [, $actor] = $this->actor();
        $artifact = $this->service()->create($this->attributes($this->initiative($actor)), $actor);
        $revision = $artifact->revisions->sole();
        $replica = $revision->replicate();

        $this->assertThrows(fn () => $replica->save(), LogicException::class);
        $this->assertThrows(fn () => $replica->saveQuietly(), LogicException::class);
        $this->assertThrows(fn () => ArtifactRevision::query()->create($revision->getAttributes()), LogicException::class);
        $this->assertThrows(fn () => $this->service()->revise($artifact, ['invalid' => static fn () => null], null, 1, 'Conteúdo inválido.', $actor), LogicException::class);
        $this->assertSame(1, $artifact->revisions()->count());
    }

    public function test_database_rejects_artifact_without_parent(): void
    {
        [$organizationB, $actorB] = $this->actor();

        $this->expectException(QueryException::class);
        DB::table('artifacts')->insert(['organization_id' => $organizationB->id, 'code' => 'ART-X', 'type' => ArtifactType::InitiativeRecord->value, 'title' => 'Inválido', 'current_revision_sequence' => 0, 'created_by' => $actorB->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_database_rejects_artifact_with_two_parents(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor);
        $project = $this->project($actor, $organization);

        $this->expectException(QueryException::class);
        DB::table('artifacts')->insert(['organization_id' => $organization->id, 'initiative_id' => $initiative->id, 'project_id' => $project->id, 'code' => 'ART-BOTH', 'type' => ArtifactType::StructuredRecord->value, 'title' => 'Inválido', 'current_revision_sequence' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_database_rejects_cross_tenant_revision_source(): void
    {
        [, $actorA] = $this->actor();
        $initiative = $this->initiative($actorA);
        [$organizationB, $actorB] = $this->actor();
        $foreignArtifact = $this->service()->create($this->attributes($this->initiative($actorB)), $actorB);
        $foreignSource = $initiative->configurationVersions()->withoutGlobalScopes()->value('id');

        $this->expectException(QueryException::class);
        DB::table('artifact_revisions')->insert([
            'organization_id' => $organizationB->id, 'artifact_id' => $foreignArtifact->id, 'sequence' => 2,
            'schema_version' => 1, 'content' => json_encode(['invalid' => true]), 'checksum' => str_repeat('0', 64),
            'source_initiative_configuration_version_id' => $foreignSource, 'changed_by' => $actorB->id,
            'change_reason' => 'Inválido', 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_service_and_database_reject_cross_tenant_parent_and_source_injection(): void
    {
        [$organizationA, $actorA] = $this->actor();
        $initiative = $this->initiative($actorA);
        [$organizationB, $actorB] = $this->actor();

        $this->assertThrows(fn () => $this->service()->create($this->attributes($initiative), $actorB), LogicException::class);
        $this->assertThrows(fn () => $this->service()->create($this->attributes($this->initiative($actorB)) + ['source_initiative_configuration_version_id' => $initiative->configurationVersions()->withoutGlobalScopes()->value('id')], $actorB), LogicException::class);

        $this->expectException(QueryException::class);
        DB::table('artifacts')->insert(['organization_id' => $organizationB->id, 'initiative_id' => $initiative->id, 'code' => 'ART-CROSS', 'type' => ArtifactType::InitiativeRecord->value, 'title' => 'Inválido', 'current_revision_sequence' => 0, 'created_by' => $actorB->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_artifact_codes_are_sequential_and_independent_per_organization(): void
    {
        [, $actorA] = $this->actor();
        $first = $this->service()->create($this->attributes($this->initiative($actorA)), $actorA);
        $second = $this->service()->create($this->attributes($this->initiative($actorA)), $actorA);
        [, $actorB] = $this->actor();
        $other = $this->service()->create($this->attributes($this->initiative($actorB)), $actorB);

        $this->assertSame('ART-000001', $first->code);
        $this->assertSame('ART-000002', $second->code);
        $this->assertSame('ART-000001', $other->code);
    }

    public function test_inactive_suspended_and_superadmin_without_temporary_access_are_rejected(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor);
        $actor->update(['is_active' => false]);
        $this->assertThrows(fn () => $this->service()->create($this->attributes($initiative), $actor->fresh()), LogicException::class);

        [, $suspended] = $this->actor();
        $suspendedInitiative = $this->initiative($suspended);
        OrganizationMembership::query()->where('organization_id', $suspendedInitiative->organization_id)->where('user_id', $suspended->id)->update(['status' => OrganizationMembershipStatus::Suspended]);
        $this->assertThrows(fn () => $this->service()->create($this->attributes($suspendedInitiative), $suspended->fresh()), LogicException::class);

        $superadmin = User::factory()->create(['global_profile' => GlobalProfile::Administrator]);
        app(OrganizationContext::class)->clear();
        $this->assertThrows(fn () => $this->service()->create($this->attributes($initiative), $superadmin), LogicException::class);
        app(OrganizationContext::class)->activatePlatformAccess($organization, collect());
        $this->assertInstanceOf(Artifact::class, $this->service()->create($this->attributes($initiative), $superadmin));
    }

    public function test_active_non_administrative_membership_is_conservatively_rejected(): void
    {
        [$organization, $administrator] = $this->actor();
        $initiative = $this->initiative($administrator);
        $member = User::factory()->create();
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $member->id, 'role_code' => OrganizationRole::Member, 'status' => OrganizationMembershipStatus::Active]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));

        $this->assertThrows(fn () => $this->service()->create($this->attributes($initiative), $member), LogicException::class);
    }

    public function test_routes_apply_server_validation_and_do_not_accept_parent_or_organization_mass_assignment(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = $this->initiative($actor);
        $foreign = Organization::factory()->create();
        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->post(route('initiatives.artifacts.store', $initiative), ['type' => ArtifactType::InitiativeRecord->value, 'title' => 'Novo', 'content' => 'not-json', 'schema_version' => 1, 'change_reason' => 'Inicial', 'organization_id' => $foreign->id, 'project_id' => 999])
            ->assertSessionHasErrors('content');
        $this->assertSame(0, Artifact::query()->count());
    }

    public function test_route_model_binding_hides_artifacts_from_another_organization(): void
    {
        [$organizationA, $actorA] = $this->actor();
        $artifact = $this->service()->create($this->attributes($this->initiative($actorA)), $actorA);
        [$organizationB, $actorB] = $this->actor();

        $this->actingAs($actorB)->withSession(['active_organization_id' => $organizationB->id])
            ->get(route('artifacts.show', $artifact))
            ->assertNotFound();
    }

    private function service(): ArtifactRevisionService
    {
        return app(ArtifactRevisionService::class);
    }

    /** @return array{0: Organization, 1: User} */
    private function actor(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'role_code' => OrganizationRole::Administrator, 'status' => OrganizationMembershipStatus::Active]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));

        return [$organization, $actor];
    }

    private function initiative(User $actor): Initiative
    {
        return app(InitiativeConfigurationService::class)->create([
            'title' => 'Iniciativa documental', 'context' => 'Contexto', 'origin' => InitiativeOrigin::Internal,
            'execution_nature' => ExecutionNature::Internal,
            'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential,
            'methodology' => ProjectMethodology::Kanban,
        ], $actor, 'Registro inicial.');
    }

    private function project(User $actor, Organization $organization): Project
    {
        $initiative = $this->initiative($actor);

        return app(InitiativeConversionService::class)->convert($initiative, ['client_id' => Client::factory()->create(['organization_id' => $organization->id])->id, 'objective' => 'Objetivo do projeto.'], $actor);
    }

    /** @return array<string, mixed> */
    private function attributes(Initiative|Project $parent): array
    {
        return [($parent instanceof Initiative ? 'initiative_id' : 'project_id') => $parent->id, 'type' => $parent instanceof Initiative ? ArtifactType::InitiativeRecord : ArtifactType::ProjectRecord, 'title' => 'Registro estruturado', 'description' => 'Descrição', 'content' => ['z' => ['second' => false, 'first' => true], 'a' => 1], 'metadata' => ['b' => 2, 'a' => 1], 'schema_version' => 1, 'change_reason' => 'Registro inicial.'];
    }
}
