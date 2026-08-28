<?php

namespace App\Services;

use App\Enums\ApplicabilityOutcome;
use App\Enums\ApplicabilityTargetType;
use App\Enums\InitiativeOrigin;
use App\Enums\InitiativeState;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Initiative;
use App\Models\NegotiationEntry;
use App\Models\OrganizationMembership;
use App\Models\PlatformApplicabilityRuleSet;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class InitiativeConversionService
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly AdaptiveConfigurationResolver $resolver,
        private readonly ApplicabilityEngine $engine,
        private readonly ProjectConfigurationService $projectConfigurations,
        private readonly ProjectContractService $projectContracts,
    ) {}

    /** @return array{available: bool, reason: string} */
    public function availability(Initiative $initiative, User $actor): array
    {
        $this->authorize($initiative, $actor, false);

        if (! ($actor->isSuperAdmin() && $this->context->isPlatformAccess()) && ! $actor->canCreateProjects()) {
            return ['available' => false, 'reason' => 'A conversão exige autorização para iniciar projetos nesta organização.'];
        }

        if ($initiative->project()->exists() || $initiative->state === InitiativeState::Converted) {
            return ['available' => false, 'reason' => 'A iniciativa já foi convertida em projeto.'];
        }
        if (in_array($initiative->state, [InitiativeState::Cancelled, InitiativeState::Archived], true)) {
            return ['available' => false, 'reason' => 'A iniciativa não está disponível para conversão.'];
        }

        $applicability = $this->applicability($initiative);
        if (! $applicability['available']) {
            return $applicability;
        }

        if ($initiative->origin === InitiativeOrigin::Commercial) {
            return $this->commercialAvailability($initiative);
        }
        if ($initiative->origin === InitiativeOrigin::ExistingContract && ! $initiative->contracts()->exists()) {
            return ['available' => false, 'reason' => 'Vincule ao menos um contrato antes de iniciar o projeto.'];
        }

        return ['available' => true, 'reason' => 'A rota operacional desta origem está disponível.'];
    }

    /** @param array{client_id: int, objective: string, justification?: string|null} $attributes */
    public function convert(Initiative $initiative, array $attributes, User $actor): Project
    {
        $this->authorize($initiative, $actor);
        $this->validateAttributes($attributes);

        return DB::transaction(function () use ($initiative, $attributes, $actor): Project {
            $locked = Initiative::query()->whereKey($initiative->id)->lockForUpdate()->firstOrFail();
            $existing = Project::query()->where('initiative_id', $locked->id)->lockForUpdate()->first();
            if ($existing instanceof Project) {
                return $existing;
            }

            $availability = $this->availability($locked, $actor);
            if (! $availability['available']) {
                throw new LogicException($availability['reason']);
            }

            $sourceVersion = $locked->configurationVersions()
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->firstOrFail();
            $client = Client::query()->whereKey($attributes['client_id'])->where('is_active', true)->first();
            if (! $client instanceof Client) {
                throw new LogicException('O cliente selecionado não pertence ao contexto ativo.');
            }
            $managerId = $this->managerId($locked, $actor);

            $project = Project::create([
                'client_id' => $client->id,
                'manager_id' => $managerId,
                'initiative_id' => $locked->id,
                'source_initiative_configuration_version_id' => $sourceVersion->id,
                'name' => $locked->title,
                'description' => $locked->context,
                'objective' => $attributes['objective'],
                'justification' => $attributes['justification'] ?? null,
                'execution_nature' => $sourceVersion->execution_nature,
                'financial_management_mode' => $sourceVersion->financial_management_mode,
                'management_level' => $sourceVersion->management_level,
                'methodology' => $sourceVersion->methodology,
                'status' => ProjectStatus::Planning,
                'is_active' => true,
            ]);

            $this->projectConfigurations->recordInitial(
                $project,
                $actor,
                'Histórico inicial criado na conversão da iniciativa '.$locked->code.'.',
            );

            $locked->contracts()
                ->whereNull('project_id')
                ->lockForUpdate()
                ->get()
                ->each(fn ($contract) => $this->projectContracts->linkToProject(
                    $contract,
                    $project,
                    $actor,
                    'Vínculo herdado na conversão da iniciativa '.$locked->code.'.',
                ));

            $locked->update([
                'state' => InitiativeState::Converted,
                'converted_at' => now(),
                'converted_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);

            ProjectActivity::record(
                $project,
                $actor,
                'initiative_converted',
                'Projeto criado a partir da iniciativa '.$locked->code.'.',
                'initiative',
                $locked->id,
                ['initiative_configuration_version_id' => $sourceVersion->id],
            );

            return $project;
        });
    }

    /** @return array{available: bool, reason: string} */
    private function applicability(Initiative $initiative): array
    {
        $ruleSet = PlatformApplicabilityRuleSet::query()
            ->where('status', 'active')
            ->whereNull('retired_at')
            ->firstOrFail();
        $context = $this->resolver->initiative(
            $initiative,
            ApplicabilityTargetType::Action,
            'initiative.conversion',
            now(),
            $ruleSet->version,
        );
        $result = $this->engine->evaluate($context, $ruleSet->rules()
            ->where('target_type', ApplicabilityTargetType::Action->value)
            ->where('target_key', 'initiative.conversion')
            ->get());

        if (in_array($result->outcome, [ApplicabilityOutcome::NotApplicable, ApplicabilityOutcome::Unavailable], true)) {
            return ['available' => false, 'reason' => $result->safeExplanation];
        }

        return ['available' => true, 'reason' => $result->safeExplanation];
    }

    /** @return array{available: bool, reason: string} */
    private function commercialAvailability(Initiative $initiative): array
    {
        $opportunity = $initiative->opportunity;
        if ($opportunity === null || $opportunity->state !== 'won') {
            return ['available' => false, 'reason' => 'A rota comercial exige oportunidade vencedora.'];
        }

        $acceptance = NegotiationEntry::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('initiative_id', $initiative->id)
            ->where('interaction_type', 'acceptance')
            ->where('decision', 'Aceita')
            ->whereNotNull('proposal_id')
            ->whereNotNull('proposal_version_id')
            ->latest('occurred_at')
            ->first();
        if ($acceptance === null) {
            return ['available' => false, 'reason' => 'A rota comercial exige aceite de proposta versionada.'];
        }

        $proposal = Proposal::query()->whereKey($acceptance->proposal_id)
            ->where('initiative_id', $initiative->id)
            ->where('opportunity_id', $opportunity->id)
            ->first();
        $version = ProposalVersion::query()->whereKey($acceptance->proposal_version_id)
            ->where('proposal_id', $acceptance->proposal_id)
            ->first();
        if ($proposal === null || $version === null
            || in_array($proposal->state, ['rejected', 'expired'], true)
            || ($version->validity_until !== null && $version->validity_until->isBefore(today()))) {
            return ['available' => false, 'reason' => 'A proposta aceita não está válida para conversão.'];
        }

        return ['available' => true, 'reason' => 'A oportunidade vencedora possui aceite válido.'];
    }

    /** @param array<string, mixed> $attributes */
    private function validateAttributes(array $attributes): void
    {
        if (array_diff(array_keys($attributes), ['client_id', 'objective', 'justification']) !== []
            || ! isset($attributes['client_id'])
            || blank(trim((string) ($attributes['objective'] ?? '')))) {
            throw new LogicException('Cliente e objetivo operacional são obrigatórios para a conversão.');
        }
    }

    private function authorize(Initiative $initiative, User $actor, bool $requireProjectCreation = true): void
    {
        if (! $actor->exists || ! $actor->is_active
            || ! $this->context->active()
            || $this->context->id() !== (int) $initiative->organization_id) {
            throw new LogicException('A iniciativa não pertence ao contexto autorizado.');
        }
        if ($actor->isSuperAdmin() && $this->context->isPlatformAccess()) {
            return;
        }
        $membership = OrganizationMembership::query()
            ->where('organization_id', $initiative->organization_id)
            ->where('user_id', $actor->id)
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->exists();
        if (! $membership || ($requireProjectCreation && ! $actor->canCreateProjects())) {
            throw new LogicException('O ator não possui autorização para iniciar projetos nesta organização.');
        }
    }

    private function managerId(Initiative $initiative, User $actor): int
    {
        if (! $actor->isSuperAdmin() || ! $this->context->isPlatformAccess()) {
            return $actor->id;
        }

        $creatorIsActiveMember = OrganizationMembership::query()
            ->where('organization_id', $initiative->organization_id)
            ->where('user_id', $initiative->created_by)
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->exists();
        if (! $creatorIsActiveMember) {
            throw new LogicException('A conversão exige responsável com vínculo organizacional ativo.');
        }

        return (int) $initiative->created_by;
    }
}
