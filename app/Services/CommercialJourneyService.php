<?php

namespace App\Services;

use App\Enums\InitiativeOrigin;
use App\Models\CommercialTransition;
use App\Models\InitialAssessment;
use App\Models\Initiative;
use App\Models\NegotiationEntry;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CommercialJourneyService
{
    public function createOpportunity(Initiative $initiative, array $data, User $actor): Opportunity
    {
        $this->authorize($initiative, $actor);

        if ($initiative->origin !== InitiativeOrigin::Commercial
            || $initiative->opportunity()->whereNull('archived_at')->exists()) {
            throw new LogicException('Jornada comercial indisponível.');
        }

        return DB::transaction(function () use ($initiative, $data, $actor): Opportunity {
            $opportunity = Opportunity::create($data + [
                'initiative_id' => $initiative->id,
                'organization_id' => $initiative->organization_id,
                'created_by' => $actor->id,
                'code' => 'OPP-'.str_pad((string) $initiative->id, 6, '0', STR_PAD_LEFT),
            ]);
            $this->record($opportunity, null, 'open', $actor, null);

            return $opportunity;
        });
    }

    public function transition(Opportunity $opportunity, string $state, User $actor, ?string $justification = null): void
    {
        $this->authorize($opportunity, $actor);

        if (($state === 'lost' && blank($justification))
            || in_array($opportunity->state, ['won', 'lost'], true)) {
            throw new LogicException('Transição comercial inválida.');
        }

        $from = $opportunity->state;
        $opportunity->update([
            'state' => $state,
            'loss_reason' => $state === 'lost' ? $justification : $opportunity->loss_reason,
        ]);
        $this->record($opportunity, $from, $state, $actor, $justification);
    }

    public function assessment(Opportunity $opportunity, array $data, User $actor): InitialAssessment
    {
        $this->authorize($opportunity, $actor);

        return InitialAssessment::create($data + [
            'opportunity_id' => $opportunity->id,
            'organization_id' => $opportunity->organization_id,
            'created_by' => $actor->id,
        ]);
    }

    public function proposal(Opportunity $opportunity, array $data, User $actor): Proposal
    {
        $this->authorize($opportunity, $actor);

        return DB::transaction(function () use ($opportunity, $data, $actor): Proposal {
            $proposal = Proposal::create([
                'organization_id' => $opportunity->organization_id,
                'initiative_id' => $opportunity->initiative_id,
                'opportunity_id' => $opportunity->id,
                'code' => 'PROP-'.str_pad((string) $opportunity->id, 6, '0', STR_PAD_LEFT),
                'created_by' => $actor->id,
            ]);
            $proposal->versions()->create($data + [
                'organization_id' => $opportunity->organization_id,
                'sequence' => 1,
                'changed_by' => $actor->id,
            ]);

            return $proposal;
        });
    }

    public function negotiation(Opportunity $opportunity, array $data, User $actor): NegotiationEntry
    {
        $this->authorize($opportunity, $actor);

        if (($data['interaction_type'] ?? null) === 'acceptance' && ! empty($data['proposal_version_id'])) {
            $version = ProposalVersion::query()
                ->whereKey($data['proposal_version_id'])
                ->where('organization_id', $opportunity->organization_id)
                ->first();
            $proposal = $version === null ? null : Proposal::query()
                ->whereKey($version->proposal_id)
                ->where('organization_id', $opportunity->organization_id)
                ->where('initiative_id', $opportunity->initiative_id)
                ->where('opportunity_id', $opportunity->id)
                ->first();

            if ($proposal === null
                || in_array($proposal->state, ['rejected', 'expired'], true)
                || ($version->validity_until !== null && $version->validity_until->isBefore(today()))) {
                throw new LogicException('Selecione uma versão válida de proposta desta oportunidade.');
            }

            $data['proposal_id'] = $proposal->id;
            $data['decision'] = 'Aceita';
        }

        return NegotiationEntry::create($data + [
            'organization_id' => $opportunity->organization_id,
            'initiative_id' => $opportunity->initiative_id,
            'opportunity_id' => $opportunity->id,
            'created_by' => $actor->id,
        ]);
    }

    private function authorize(object $subject, User $actor): void
    {
        if (! $actor->is_active
            || ! app(OrganizationContext::class)->active()
            || app(OrganizationContext::class)->id() !== $subject->organization_id
            || ((! $actor->isSuperAdmin() || ! app(OrganizationContext::class)->isPlatformAccess())
                && ! $actor->administersCurrentOrganization())) {
            throw new LogicException('Operação comercial não autorizada.');
        }
    }

    private function record(Opportunity $opportunity, ?string $from, string $to, User $actor, ?string $justification): void
    {
        CommercialTransition::create([
            'organization_id' => $opportunity->organization_id,
            'subject_type' => 'opportunity',
            'subject_id' => $opportunity->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $actor->id,
            'justification' => $justification,
        ]);
    }
}
