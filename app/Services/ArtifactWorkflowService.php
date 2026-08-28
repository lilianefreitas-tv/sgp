<?php

namespace App\Services;

use App\Enums\ApplicabilityOutcome;
use App\Enums\ApplicabilityTargetType;
use App\Enums\ArtifactWorkflowDecisionType;
use App\Enums\ArtifactWorkflowState;
use App\Enums\DocumentRole;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Artifact;
use App\Models\ArtifactRevision;
use App\Models\ArtifactWorkflowDecision;
use App\Models\ArtifactWorkflowRound;
use App\Models\DocumentRoleAssignment;
use App\Models\PlatformApplicabilityRuleSet;
use App\Models\User;
use App\ValueObjects\ApplicabilityResult;
use Illuminate\Support\Facades\DB;
use LogicException;

class ArtifactWorkflowService
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly AdaptiveConfigurationResolver $resolver,
        private readonly ApplicabilityEngine $engine,
    ) {}

    public function assign(Artifact $artifact, User $user, DocumentRole $role, User $actor): DocumentRoleAssignment
    {
        return DB::transaction(function () use ($artifact, $user, $role, $actor): DocumentRoleAssignment {
            $organizationId = $this->authorizeAdministrator($actor, $artifact->organization_id);
            $locked = $this->lockedArtifact($artifact, $organizationId);
            if (! $user->is_active || ! $user->organizationMemberships()->where('organization_id', $organizationId)->where('status', OrganizationMembershipStatus::Active->value)->exists()) {
                throw new LogicException('O usuário selecionado não possui membership ativo nesta organização.');
            }
            [$parentKey, $parentId] = $this->parent($locked);
            $current = DocumentRoleAssignment::query()
                ->where('organization_id', $organizationId)->where($parentKey, $parentId)
                ->where('user_id', $user->id)->where('role', $role)->whereNull('effective_until')
                ->lockForUpdate()->first();
            if ($current !== null) {
                return $current;
            }

            return DocumentRoleAssignment::assignThroughService([
                'organization_id' => $organizationId,
                'initiative_id' => $locked->initiative_id,
                'project_id' => $locked->project_id,
                'user_id' => $user->id,
                'role' => $role,
                'effective_from' => now(),
                'assigned_by' => $actor->id,
            ]);
        });
    }

    public function submit(Artifact $artifact, User $actor, string $reason): ArtifactWorkflowRound
    {
        return DB::transaction(function () use ($artifact, $actor, $reason): ArtifactWorkflowRound {
            $organizationId = $this->authorizeRole($actor, $artifact->organization_id, DocumentRole::Author, $artifact);
            $locked = $this->lockedArtifact($artifact, $organizationId);
            if ($locked->archived_at !== null) {
                throw new LogicException('Artefatos arquivados não podem ser submetidos.');
            }
            if (in_array($locked->workflow_state, [ArtifactWorkflowState::InReview, ArtifactWorkflowState::AwaitingApproval], true)) {
                throw new LogicException('O artefato já possui uma rodada de análise aberta.');
            }
            $revision = $locked->revisions()->where('sequence', $locked->current_revision_sequence)->lockForUpdate()->firstOrFail();
            $lastRound = $locked->workflowRounds()->lockForUpdate()->latest('sequence')->first();
            if ($lastRound?->artifact_revision_id === $revision->id && $lastRound->state === ArtifactWorkflowState::ChangesRequested) {
                throw new LogicException('Registre uma nova revisão antes de reenviar o artefato.');
            }
            if ($lastRound?->artifact_revision_id === $revision->id && in_array($lastRound->state, [ArtifactWorkflowState::Approved, ArtifactWorkflowState::Rejected], true)) {
                throw new LogicException('Uma rodada encerrada não pode ser reaberta; registre uma nova revisão.');
            }
            $applicability = $this->applicability($locked);
            if (in_array($applicability->outcome, [ApplicabilityOutcome::NotApplicable, ApplicabilityOutcome::Unavailable], true)) {
                throw new LogicException($applicability->safeExplanation);
            }
            $round = ArtifactWorkflowRound::createThroughService([
                'organization_id' => $organizationId,
                'artifact_id' => $locked->id,
                'artifact_revision_id' => $revision->id,
                'sequence' => ((int) $lastRound?->sequence) + 1,
                'state' => ArtifactWorkflowState::InReview,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'source_initiative_configuration_version_id' => $revision->source_initiative_configuration_version_id,
                'source_project_configuration_version_id' => $revision->source_project_configuration_version_id,
                'applicability_outcome' => $applicability->outcome->value,
                'applicability_reason_code' => $applicability->reasonCode,
            ]);
            $this->recordDecision($round, $revision, $actor, DocumentRole::Author, ArtifactWorkflowDecisionType::Submitted, $reason);
            $locked->update(['workflow_state' => ArtifactWorkflowState::InReview]);

            return $round->fresh(['decisions.actor']) ?? $round;
        });
    }

    public function decide(ArtifactWorkflowRound $round, User $actor, ArtifactWorkflowDecisionType $decision, string $reason): ArtifactWorkflowRound
    {
        return DB::transaction(function () use ($round, $actor, $decision, $reason): ArtifactWorkflowRound {
            if ($decision === ArtifactWorkflowDecisionType::Submitted) {
                throw new LogicException('Decisão inválida para encerramento da rodada.');
            }
            $locked = ArtifactWorkflowRound::query()->where('organization_id', $this->context->id())->lockForUpdate()->find($round->id);
            if ($locked === null || ! in_array($locked->state, [ArtifactWorkflowState::InReview, ArtifactWorkflowState::AwaitingApproval], true)) {
                throw new LogicException('A rodada não está aberta para decisão.');
            }
            $artifact = Artifact::query()->where('organization_id', $locked->organization_id)->lockForUpdate()->findOrFail($locked->artifact_id);
            if ($artifact->archived_at !== null) {
                throw new LogicException('Artefatos arquivados não aceitam decisões.');
            }
            if ($artifact->current_revision_sequence !== $locked->revision->sequence) {
                throw new LogicException('Somente a revisão corrente pode receber decisão.');
            }
            $role = match ($locked->state) {
                ArtifactWorkflowState::InReview => DocumentRole::Reviewer,
                ArtifactWorkflowState::AwaitingApproval => DocumentRole::Approver,
                default => throw new LogicException('Estado documental inválido.'),
            };
            $this->authorizeRole($actor, $locked->organization_id, $role, $artifact);
            if ($locked->state === ArtifactWorkflowState::InReview && ! in_array($decision, [ArtifactWorkflowDecisionType::ChangesRequested, ArtifactWorkflowDecisionType::ForwardedForApproval], true)) {
                throw new LogicException('O revisor deve solicitar ajustes ou encaminhar a revisão para aprovação.');
            }
            if ($locked->state === ArtifactWorkflowState::AwaitingApproval && ! in_array($decision, [ArtifactWorkflowDecisionType::ChangesRequested, ArtifactWorkflowDecisionType::Approved, ArtifactWorkflowDecisionType::Rejected], true)) {
                throw new LogicException('O aprovador deve aprovar, rejeitar ou solicitar ajustes.');
            }
            if ($locked->state === ArtifactWorkflowState::AwaitingApproval && $locked->applicability_outcome === ApplicabilityOutcome::Required->value && $locked->submitted_by === $actor->id) {
                throw new LogicException('A governança aplicável exige segregação entre autor e aprovador.');
            }
            $state = match ($decision) {
                ArtifactWorkflowDecisionType::ChangesRequested => ArtifactWorkflowState::ChangesRequested,
                ArtifactWorkflowDecisionType::ForwardedForApproval => ArtifactWorkflowState::AwaitingApproval,
                ArtifactWorkflowDecisionType::Approved => ArtifactWorkflowState::Approved,
                ArtifactWorkflowDecisionType::Rejected => ArtifactWorkflowState::Rejected,
                default => throw new LogicException('Decisão inválida.'),
            };
            ArtifactWorkflowRound::transition($locked, $state, $state !== ArtifactWorkflowState::AwaitingApproval);
            $this->recordDecision($locked, $locked->revision, $actor, $role, $decision, $reason);
            $artifact->update(['workflow_state' => $state]);

            return $locked->fresh(['revision', 'decisions.actor']) ?? $locked;
        });
    }

    public function latestApproved(Artifact $artifact): ?ArtifactWorkflowRound
    {
        return $artifact->workflowRounds()->where('state', ArtifactWorkflowState::Approved)
            ->with(['revision', 'sourceInitiativeConfigurationVersion', 'sourceProjectConfigurationVersion', 'decisions' => fn ($query) => $query->where('decision', ArtifactWorkflowDecisionType::Approved), 'decisions.actor'])
            ->latest('closed_at')->first();
    }

    public function applicabilityFor(Artifact $artifact): ApplicabilityResult
    {
        return $this->applicability($artifact);
    }

    private function applicability(Artifact $artifact): ApplicabilityResult
    {
        $set = PlatformApplicabilityRuleSet::query()->where('status', 'active')->whereNull('retired_at')->firstOrFail();
        $context = $artifact->initiative_id !== null
            ? $this->resolver->initiative($artifact->initiative, ApplicabilityTargetType::Action, 'artifact.workflow.approval', now(), $set->version)
            : $this->resolver->project($artifact->project, ApplicabilityTargetType::Action, 'artifact.workflow.approval', now(), $set->version);

        return $this->engine->evaluate($context, $set->rules()->get());
    }

    private function recordDecision(ArtifactWorkflowRound $round, ArtifactRevision $revision, User $actor, DocumentRole $role, ArtifactWorkflowDecisionType $decision, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new LogicException('A justificativa é obrigatória.');
        }
        ArtifactWorkflowDecision::createThroughService([
            'organization_id' => $round->organization_id,
            'round_id' => $round->id,
            'artifact_revision_id' => $revision->id,
            'actor_id' => $actor->id,
            'role' => $role,
            'decision' => $decision,
            'justification' => $reason,
            'metadata' => ['role_accumulation' => $round->submitted_by === $actor->id],
            'decided_at' => now(),
        ]);
    }

    private function authorizeAdministrator(User $actor, int $organizationId): int
    {
        $this->authorizeContext($actor, $organizationId);
        if ($actor->isSuperAdmin() && $this->context->isPlatformAccess()) {
            return $organizationId;
        }
        if (! $actor->administersCurrentOrganization()) {
            throw new LogicException('A atribuição de papéis exige Owner ou Administrator ativo.');
        }

        return $organizationId;
    }

    private function authorizeRole(User $actor, int $organizationId, DocumentRole $role, Artifact $artifact): int
    {
        $this->authorizeContext($actor, $organizationId);
        if ($actor->isSuperAdmin() && $this->context->isPlatformAccess()) {
            return $organizationId;
        }
        [$parentKey, $parentId] = $this->parent($artifact);
        $assigned = DocumentRoleAssignment::query()->where('organization_id', $organizationId)
            ->where($parentKey, $parentId)->where('user_id', $actor->id)->where('role', $role)
            ->whereNull('effective_until')->exists();
        if (! $assigned) {
            throw new LogicException('O usuário não possui o papel documental ativo necessário.');
        }

        return $organizationId;
    }

    private function authorizeContext(User $actor, int $organizationId): void
    {
        if (! $actor->is_active || ! $this->context->active() || $this->context->id() !== $organizationId) {
            throw new LogicException('Contexto organizacional inválido.');
        }
        if ($actor->isSuperAdmin()) {
            if (! $this->context->isPlatformAccess()) {
                throw new LogicException('Superadministradores exigem acesso temporário explícito.');
            }

            return;
        }
        if (! $actor->organizationMemberships()->where('organization_id', $organizationId)->where('status', OrganizationMembershipStatus::Active->value)->exists()) {
            throw new LogicException('Membership ativo obrigatório.');
        }
    }

    private function lockedArtifact(Artifact $artifact, int $organizationId): Artifact
    {
        return Artifact::query()->where('organization_id', $organizationId)->lockForUpdate()->find($artifact->id)
            ?? throw new LogicException('Artefato não encontrado no contexto ativo.');
    }

    /** @return array{0: string, 1: int} */
    private function parent(Artifact $artifact): array
    {
        return $artifact->initiative_id !== null ? ['initiative_id', $artifact->initiative_id] : ['project_id', $artifact->project_id];
    }
}
