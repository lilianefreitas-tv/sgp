<?php

namespace App\Services;

use App\Enums\InitiativeOrigin;
use App\Models\{CommercialTransition, InitialAssessment, Initiative, NegotiationEntry, Opportunity, Proposal, User};
use Illuminate\Support\Facades\DB;
use LogicException;

class CommercialJourneyService
{
    public function createOpportunity(Initiative $initiative, array $data, User $actor): Opportunity
    {
        $this->authorize($initiative, $actor);
        if ($initiative->origin !== InitiativeOrigin::Commercial || $initiative->opportunity()->whereNull('archived_at')->exists()) throw new LogicException('Jornada comercial indisponível.');
        return DB::transaction(function () use ($initiative, $data, $actor) { $opportunity = Opportunity::create($data + ['initiative_id'=>$initiative->id,'organization_id'=>$initiative->organization_id,'created_by'=>$actor->id,'code'=>'OPP-'.str_pad((string)$initiative->id,6,'0',STR_PAD_LEFT)]); $this->record($opportunity,null,'open',$actor,null); return $opportunity; });
    }
    public function transition(Opportunity $opportunity, string $state, User $actor, ?string $justification = null): void { $this->authorize($opportunity,$actor); if (($state==='lost'&&blank($justification))||in_array($opportunity->state,['won','lost'],true)) throw new LogicException('Transição comercial inválida.'); $from=$opportunity->state; $opportunity->update(['state'=>$state,'loss_reason'=>$state==='lost'?$justification:$opportunity->loss_reason]); $this->record($opportunity,$from,$state,$actor,$justification); }
    public function assessment(Opportunity $opportunity,array $data,User $actor): InitialAssessment { $this->authorize($opportunity,$actor); return InitialAssessment::create($data+['opportunity_id'=>$opportunity->id,'organization_id'=>$opportunity->organization_id,'created_by'=>$actor->id]); }
    public function proposal(Opportunity $opportunity,array $data,User $actor): Proposal { $this->authorize($opportunity,$actor); return DB::transaction(function()use($opportunity,$data,$actor){$p=Proposal::create(['organization_id'=>$opportunity->organization_id,'initiative_id'=>$opportunity->initiative_id,'opportunity_id'=>$opportunity->id,'code'=>'PROP-'.str_pad((string)$opportunity->id,6,'0',STR_PAD_LEFT),'created_by'=>$actor->id]);$p->versions()->create($data+['organization_id'=>$opportunity->organization_id,'sequence'=>1,'changed_by'=>$actor->id]);return $p;}); }
    public function negotiation(Opportunity $opportunity,array $data,User $actor): NegotiationEntry { $this->authorize($opportunity,$actor); return NegotiationEntry::create($data+['organization_id'=>$opportunity->organization_id,'initiative_id'=>$opportunity->initiative_id,'opportunity_id'=>$opportunity->id,'created_by'=>$actor->id]); }
    private function authorize(object $subject,User $actor): void { if (!$actor->is_active || !app(OrganizationContext::class)->active() || app(OrganizationContext::class)->id() !== $subject->organization_id || ((!$actor->isSuperAdmin() || !app(OrganizationContext::class)->isPlatformAccess()) && !$actor->administersCurrentOrganization())) throw new LogicException('Operação comercial não autorizada.'); }
    private function record(Opportunity $o,?string $from,string $to,User $actor,?string $just): void { CommercialTransition::create(['organization_id'=>$o->organization_id,'subject_type'=>'opportunity','subject_id'=>$o->id,'from_state'=>$from,'to_state'=>$to,'changed_by'=>$actor->id,'justification'=>$just]); }
}
