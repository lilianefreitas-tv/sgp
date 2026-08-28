<?php

namespace Tests\Feature;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectMethodology;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\CommercialJourneyService;
use App\Services\InitiativeConfigurationService;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CommercialJourneyTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { app(OrganizationContext::class)->clear(); parent::tearDown(); }
    public function test_commercial_initiative_creates_opportunity_and_transition_history(): void
    {
        [, $actor] = $this->actor(); $initiative = $this->initiative($actor, InitiativeOrigin::Commercial); $service = app(CommercialJourneyService::class);
        $opportunity = $service->createOpportunity($initiative, ['title'=>'Nova oportunidade','priority'=>'high'], $actor);
        $service->transition($opportunity, 'qualified', $actor, 'Qualificada');
        $this->assertSame($initiative->organization_id, $opportunity->organization_id); $this->assertSame('qualified', $opportunity->fresh()->state); $this->assertDatabaseCount('commercial_transitions',2);
    }
    public function test_non_commercial_origins_and_duplicate_active_opportunity_are_blocked(): void
    {
        [, $actor] = $this->actor(); $service = app(CommercialJourneyService::class);
        foreach ([InitiativeOrigin::Internal,InitiativeOrigin::Direct,InitiativeOrigin::ExistingContract] as $origin) { try {$service->createOpportunity($this->initiative($actor,$origin),['title'=>'x','priority'=>'normal'],$actor);$this->fail();}catch(LogicException){$this->assertTrue(true);} }
        $initiative=$this->initiative($actor,InitiativeOrigin::Commercial);$service->createOpportunity($initiative,['title'=>'x','priority'=>'normal'],$actor);$this->expectException(LogicException::class);$service->createOpportunity($initiative,['title'=>'y','priority'=>'normal'],$actor);
    }
    public function test_loss_requires_reason_and_final_state_cannot_reopen(): void
    {
        [, $actor]=$this->actor();$s=app(CommercialJourneyService::class);$o=$s->createOpportunity($this->initiative($actor,InitiativeOrigin::Commercial),['title'=>'x','priority'=>'normal'],$actor);try{$s->transition($o,'lost',$actor);$this->fail();}catch(LogicException){$this->assertSame('open',$o->fresh()->state);} $s->transition($o,'lost',$actor,'Sem aderência');$this->expectException(LogicException::class);$s->transition($o,'qualified',$actor);
    }
    public function test_multiple_assessments_proposal_version_and_negotiation_are_tenant_bound(): void
    {
        [, $actor]=$this->actor();$s=app(CommercialJourneyService::class);$o=$s->createOpportunity($this->initiative($actor,InitiativeOrigin::Commercial),['title'=>'x','priority'=>'normal'],$actor);$s->assessment($o,['state'=>'consolidated','needs'=>'Necessidades'],$actor);$s->assessment($o,['state'=>'validated','needs'=>'Validadas'],$actor);$proposal=$s->proposal($o,['scope_summary'=>'Escopo','pricing_model'=>'fixed'],$actor);$entry=$s->negotiation($o,['interaction_type'=>'acceptance','occurred_at'=>now(),'summary'=>'Aceite'],$actor);$this->assertSame(2,$o->assessments()->count());$this->assertSame(1,$proposal->versions()->count());$this->assertSame($o->id,$entry->opportunity_id);
    }
    public function test_inactive_or_cross_tenant_actor_is_rejected(): void
    {
        [$organization,$actor]=$this->actor();$initiative=$this->initiative($actor,InitiativeOrigin::Commercial);$actor->update(['is_active'=>false]);$this->expectException(LogicException::class);app(CommercialJourneyService::class)->createOpportunity($initiative,['title'=>'x','priority'=>'normal'],$actor->fresh());
    }
    public function test_tenant_cannot_view_or_mutate_commercial_records_from_another_tenant(): void
    {
        [, $actorA] = $this->actor(); $service = app(CommercialJourneyService::class); $initiative = $this->initiative($actorA, InitiativeOrigin::Commercial); $opportunity = $service->createOpportunity($initiative, ['title'=>'A','priority'=>'normal'], $actorA);
        [$organizationB, $actorB] = $this->actor();
        $this->actingAs($actorB)->get(route('commercial.show', $initiative))->assertNotFound();
        try { $service->transition($opportunity, 'qualified', $actorB, 'Tentativa'); $this->fail(); } catch (LogicException) { $this->assertSame('open', $opportunity->fresh()->state); }
        $this->assertNotSame($organizationB->id, $opportunity->organization_id);
    }

    public function test_direct_routes_for_non_commercial_initiatives_are_blocked(): void
    {
        [$organization, $actor] = $this->actor();
        $membership = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $actor->id)->firstOrFail();
        $initiatives = [];
        foreach ([InitiativeOrigin::Internal, InitiativeOrigin::Direct, InitiativeOrigin::ExistingContract] as $origin) {
            $initiatives[] = $this->initiative($actor, $origin);
        }
        foreach ($initiatives as $initiative) {
            app(OrganizationContext::class)->activate($membership, collect([$membership]));
            $this->actingAs($actor)->get(route('commercial.show', $initiative))->assertNotFound();
        }
    }

    public function test_proposal_versions_are_append_only_and_preserve_previous_version(): void
    {
        [, $actor] = $this->actor(); $service = app(CommercialJourneyService::class); $opportunity = $service->createOpportunity($this->initiative($actor, InitiativeOrigin::Commercial), ['title'=>'A','priority'=>'normal'], $actor); $proposal = $service->proposal($opportunity, ['scope_summary'=>'V1'], $actor); $first = $proposal->versions()->firstOrFail();
        try { $first->update(['scope_summary'=>'Sobrescrita']); $this->fail(); } catch (LogicException) { $this->assertSame('V1', $first->fresh()->scope_summary); }
        $second = $proposal->versions()->create(['organization_id'=>$proposal->organization_id,'sequence'=>2,'scope_summary'=>'V2','changed_by'=>$actor->id]);
        $this->assertSame(2, $proposal->versions()->count()); $this->assertSame('V1', $first->fresh()->scope_summary); $this->assertSame('V2', $second->scope_summary);
    }

    public function test_acceptance_entry_keeps_the_exact_proposal_version_after_later_version(): void
    {
        [, $actor] = $this->actor(); $service = app(CommercialJourneyService::class); $opportunity = $service->createOpportunity($this->initiative($actor, InitiativeOrigin::Commercial), ['title'=>'A','priority'=>'normal'], $actor); $proposal = $service->proposal($opportunity, ['scope_summary'=>'V1'], $actor); $accepted = $proposal->versions()->firstOrFail();
        $entry = $service->negotiation($opportunity, ['interaction_type'=>'acceptance','occurred_at'=>now(),'proposal_id'=>$proposal->id,'proposal_version_id'=>$accepted->id,'decision'=>'Aceita'], $actor);
        $proposal->versions()->create(['organization_id'=>$proposal->organization_id,'sequence'=>2,'scope_summary'=>'V2','changed_by'=>$actor->id]);
        $this->assertSame($accepted->id, $entry->fresh()->proposal_version_id); $this->assertSame($proposal->id, $entry->proposal_id);
    }
    private function actor(): array { $o=Organization::factory()->create();$u=User::factory()->create();$m=OrganizationMembership::factory()->create(['organization_id'=>$o->id,'user_id'=>$u->id,'role_code'=>OrganizationRole::Administrator,'status'=>OrganizationMembershipStatus::Active]);app(OrganizationContext::class)->activate($m,collect([$m]));return[$o,$u]; }
    private function initiative(User $actor, InitiativeOrigin $origin): Initiative { return app(InitiativeConfigurationService::class)->create(['title'=>'Iniciativa','origin'=>$origin,'execution_nature'=>ExecutionNature::Internal,'financial_management_mode'=>FinancialManagementMode::NotApplicable,'management_level'=>ManagementLevel::Essential,'methodology'=>ProjectMethodology::Kanban],$actor,'Inicial'); }
}
