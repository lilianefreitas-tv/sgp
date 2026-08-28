<?php

namespace Tests\Feature;

use App\Enums\ContractEntryMode;
use App\Enums\ContractStatus;
use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\InitiativeState;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectMethodology;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\ProjectContract;
use App\Models\User;
use App\Services\CommercialJourneyService;
use App\Services\InitiativeConfigurationService;
use App\Services\InitiativeConversionService;
use App\Services\OrganizationContext;
use App\Services\ProjectContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class InitiativeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_edit_updates_content_and_creates_configuration_version_with_audit(): void
    {
        [$organization, $actor, $initiative] = $this->scenario();

        $updated = app(InitiativeConfigurationService::class)->update($initiative, $this->attributes([
            'title' => 'Título corrigido',
            'methodology' => ProjectMethodology::Scrum,
        ]), $actor, 'Correção homologada.', 0);

        $this->assertSame('Título corrigido', $updated->title);
        $this->assertSame(ProjectMethodology::Scrum, $updated->methodology);
        $this->assertSame(2, $updated->configurationVersions()->count());
        $this->assertDatabaseHas('organization_audit_events', [
            'organization_id' => $organization->id,
            'action' => 'initiative.updated',
            'resource_id' => $initiative->id,
        ]);
    }

    public function test_title_only_edit_is_audited_without_creating_configuration_version(): void
    {
        [, $actor, $initiative] = $this->scenario();
        app(InitiativeConfigurationService::class)->update($initiative, $this->attributes([
            'title' => 'Ajuste textual',
        ]), $actor, 'Correção textual.', 0);

        $this->assertSame(1, $initiative->configurationVersions()->count());
        $this->assertSame('Ajuste textual', $initiative->fresh()->title);
    }

    public function test_origin_can_change_before_dependencies_but_not_after_commercial_journey_starts(): void
    {
        [, $actor, $initiative] = $this->scenario();
        $service = app(InitiativeConfigurationService::class);
        $service->update($initiative, $this->attributes(['origin' => InitiativeOrigin::Commercial]), $actor, 'Ajuste da origem.', 0);
        app(CommercialJourneyService::class)->createOpportunity($initiative->fresh(), ['title' => 'Oportunidade', 'priority' => 'normal'], $actor);

        $this->assertThrows(fn () => $service->update(
            $initiative->fresh(),
            $this->attributes(['origin' => InitiativeOrigin::Internal]),
            $actor,
            'Tentativa incompatível.',
            1,
        ), LogicException::class);
    }

    public function test_archive_hides_from_active_filter_and_restore_returns_to_draft(): void
    {
        [$organization, $actor, $initiative] = $this->scenario();
        $service = app(InitiativeConfigurationService::class);
        $archived = $service->archive($initiative, $actor, 'Cadastro feito por engano.', 0);

        $this->assertSame(InitiativeState::Archived, $archived->state);
        $this->assertNotNull($archived->archived_at);
        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->get(route('initiatives.index'))->assertOk()->assertDontSee($initiative->code);
        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->get(route('initiatives.index', ['status' => 'archived']))->assertOk()->assertSee($initiative->code);

        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->patch(route('initiatives.restore', $initiative), [
                'justification' => 'Retomada autorizada.',
                'lock_version' => 1,
            ])->assertRedirect(route('initiatives.show', $initiative));
        $restored = $initiative->fresh();
        $this->assertSame(InitiativeState::Draft, $restored->state);
        $this->assertNull($restored->archived_at);
    }

    public function test_cancelled_initiative_cannot_be_edited_or_converted(): void
    {
        [, $actor, $initiative] = $this->scenario();
        $cancelled = app(InitiativeConfigurationService::class)->cancel($initiative, $actor, 'Demanda encerrada.', 0);

        $this->assertSame(InitiativeState::Cancelled, $cancelled->state);
        $this->assertFalse(app(InitiativeConversionService::class)->availability($cancelled, $actor)['available']);
        $this->assertThrows(fn () => app(InitiativeConfigurationService::class)->update(
            $cancelled, $this->attributes(['title' => 'Indevido']), $actor, 'Não permitido.', 1,
        ), LogicException::class);
    }

    public function test_standalone_contract_can_be_linked_to_existing_contract_initiative(): void
    {
        [, $actor, $initiative] = $this->scenario(InitiativeOrigin::ExistingContract);
        $contract = app(ProjectContractService::class)->create($this->contractData(), $actor);

        $linked = app(ProjectContractService::class)->linkToInitiative(
            $contract, $initiative, $actor, 'Fundamentação contratual selecionada.', 0,
        );

        $this->assertSame($initiative->id, $linked->initiative_id);
        $this->assertSame(2, $linked->versions->max('version'));
        $this->assertTrue(app(InitiativeConversionService::class)->availability($initiative->fresh(), $actor)['available']);
        $this->assertDatabaseHas('organization_audit_events', ['action' => 'initiative.contract_linked', 'resource_id' => $initiative->id]);
    }

    public function test_contract_cannot_be_silently_transferred_between_initiatives(): void
    {
        [, $actor, $first] = $this->scenario(InitiativeOrigin::ExistingContract);
        $second = app(InitiativeConfigurationService::class)->create($this->attributes(['title' => 'Segunda iniciativa']), $actor, 'Registro inicial.');
        $contract = app(ProjectContractService::class)->create($this->contractData(['initiative_id' => $first->id]), $actor);

        $this->assertThrows(fn () => app(ProjectContractService::class)->linkToInitiative(
            $contract, $second, $actor, 'Transferência indevida.', 0,
        ), LogicException::class);
    }

    public function test_stale_lock_version_blocks_concurrent_update(): void
    {
        [, $actor, $initiative] = $this->scenario();
        app(InitiativeConfigurationService::class)->update($initiative, $this->attributes(['title' => 'Primeiro ajuste']), $actor, 'Primeiro ajuste.', 0);

        $this->assertThrows(fn () => app(InitiativeConfigurationService::class)->update(
            $initiative->fresh(), $this->attributes(['title' => 'Segundo ajuste']), $actor, 'Concorrência.', 0,
        ), LogicException::class);
    }

    public function test_cross_tenant_lifecycle_change_is_rejected(): void
    {
        [, , $initiative] = $this->scenario();
        [, $otherActor] = $this->actor();

        $this->assertThrows(fn () => app(InitiativeConfigurationService::class)->archive(
            $initiative, $otherActor, 'Tentativa cross-tenant.', 0,
        ), LogicException::class);
    }

    public function test_same_tenant_member_who_is_not_author_or_manager_cannot_change_lifecycle(): void
    {
        [$organization, , $initiative] = $this->scenario();
        $member = User::factory()->create();
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role_code' => OrganizationRole::Member,
            'status' => OrganizationMembershipStatus::Active,
        ]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));

        $this->assertThrows(fn () => app(InitiativeConfigurationService::class)->archive(
            $initiative, $member, 'Sem autorização.', 0,
        ), LogicException::class);
        $this->actingAs($member)->withSession(['active_organization_id' => $organization->id])
            ->get(route('initiatives.show', $initiative))->assertOk();
        $this->actingAs($member)->withSession(['active_organization_id' => $organization->id])
            ->get(route('initiatives.edit', $initiative))->assertForbidden();
    }

    /** @return array{0: Organization, 1: User, 2: Initiative} */
    private function scenario(InitiativeOrigin $origin = InitiativeOrigin::Internal): array
    {
        [$organization, $actor] = $this->actor();
        $initiative = app(InitiativeConfigurationService::class)->create($this->attributes(['origin' => $origin]), $actor, 'Registro inicial.');

        return [$organization, $actor, $initiative];
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

    /** @param array<string, mixed> $overrides */
    private function attributes(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Iniciativa de ciclo de vida',
            'context' => 'Contexto controlado.',
            'origin' => InitiativeOrigin::Internal,
            'execution_nature' => ExecutionNature::Internal,
            'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential,
            'methodology' => ProjectMethodology::Kanban,
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function contractData(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Contrato independente',
            'contract_kind' => 'private',
            'entry_mode' => ContractEntryMode::Editor,
            'status' => ContractStatus::Draft,
            'content' => '<p>Instrumento contratual.</p>',
            'reason' => 'Registro inicial.',
        ];
    }
}
