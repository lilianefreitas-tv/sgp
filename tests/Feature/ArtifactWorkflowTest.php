<?php

namespace Tests\Feature;

use App\Enums\ArtifactType;
use App\Enums\ArtifactWorkflowDecisionType;
use App\Enums\ArtifactWorkflowState;
use App\Enums\DocumentRole;
use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectMethodology;
use App\Models\Artifact;
use App\Models\ArtifactWorkflowDecision;
use App\Models\DocumentRoleAssignment;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use App\Services\ArtifactRevisionService;
use App\Services\ArtifactWorkflowService;
use App\Services\InitiativeConfigurationService;
use App\Services\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class ArtifactWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_assigns_roles_and_approves_the_exact_current_revision(): void
    {
        [$organization, $actor] = $this->actor();
        $artifact = $this->artifact($actor);
        foreach (DocumentRole::cases() as $role) {
            app(ArtifactWorkflowService::class)->assign($artifact, $actor, $role, $actor);
        }

        $round = app(ArtifactWorkflowService::class)->submit($artifact, $actor, 'Pronto para análise.');
        app(ArtifactWorkflowService::class)->decide($round, $actor, ArtifactWorkflowDecisionType::ForwardedForApproval, 'Revisão técnica concluída.');
        app(ArtifactWorkflowService::class)->decide($round, $actor, ArtifactWorkflowDecisionType::Approved, 'Conteúdo aprovado.');
        $approved = app(ArtifactWorkflowService::class)->latestApproved($artifact);

        $this->assertCount(3, DocumentRoleAssignment::query()->get());
        $this->assertSame(ArtifactWorkflowState::Approved, $artifact->fresh()->workflow_state);
        $this->assertSame($artifact->revisions()->sole()->id, $approved->artifact_revision_id);
        $this->assertSame($artifact->revisions()->sole()->checksum, $approved->revision->checksum);
        $this->assertCount(1, $approved->decisions);
        $this->assertSame(3, $approved->decisions()->count());
    }

    public function test_complete_governance_requires_a_different_approver(): void
    {
        [$organization, $author] = $this->actor();
        $artifact = $this->artifact($author, ManagementLevel::Complete);
        $approver = $this->member($organization);
        $workflow = app(ArtifactWorkflowService::class);
        $workflow->assign($artifact, $author, DocumentRole::Author, $author);
        $workflow->assign($artifact, $author, DocumentRole::Reviewer, $author);
        $workflow->assign($artifact, $author, DocumentRole::Approver, $author);
        $workflow->assign($artifact, $approver, DocumentRole::Approver, $author);
        $round = $workflow->submit($artifact, $author, 'Submissão segregada.');
        $workflow->decide($round, $author, ArtifactWorkflowDecisionType::ForwardedForApproval, 'Revisão técnica concluída.');

        $this->assertThrows(fn () => $workflow->decide($round, $author, ArtifactWorkflowDecisionType::Approved, 'Autoaprovação.'), LogicException::class);
        $approved = $workflow->decide($round, $approver, ArtifactWorkflowDecisionType::Approved, 'Aprovação independente.');
        $this->assertSame(ArtifactWorkflowState::Approved, $approved->state);
    }

    public function test_changes_requested_requires_a_new_revision_before_resubmission(): void
    {
        [, $actor] = $this->actor();
        $artifact = $this->artifact($actor);
        $workflow = app(ArtifactWorkflowService::class);
        $workflow->assign($artifact, $actor, DocumentRole::Author, $actor);
        $workflow->assign($artifact, $actor, DocumentRole::Reviewer, $actor);
        $first = $workflow->submit($artifact, $actor, 'Primeira submissão.');
        $workflow->decide($first, $actor, ArtifactWorkflowDecisionType::ChangesRequested, 'Ajustar o conteúdo.');
        $this->assertThrows(fn () => $workflow->submit($artifact, $actor, 'Sem alterar a revisão.'), LogicException::class);

        app(ArtifactRevisionService::class)->revise($artifact, ['field' => 'adjusted'], null, 1, 'Ajustes solicitados.', $actor);
        $second = $workflow->submit($artifact, $actor, 'Ajustes realizados.');

        $this->assertSame(2, $second->sequence);
        $this->assertSame(ArtifactWorkflowState::InReview, $artifact->fresh()->workflow_state);
        $this->assertNotSame($first->artifact_revision_id, $second->artifact_revision_id);
    }

    public function test_reviewer_hands_off_to_approver_and_each_actor_sees_the_correct_pending_item(): void
    {
        [$organization, $author] = $this->actor();
        $artifact = $this->artifact($author);
        $reviewer = $this->member($organization);
        $approver = $this->member($organization);
        $workflow = app(ArtifactWorkflowService::class);
        $workflow->assign($artifact, $author, DocumentRole::Author, $author);
        $workflow->assign($artifact, $reviewer, DocumentRole::Reviewer, $author);
        $workflow->assign($artifact, $approver, DocumentRole::Approver, $author);
        $round = $workflow->submit($artifact, $author, 'Pronto para revisão técnica.');

        $this->actingAs($reviewer)->withSession(['active_organization_id' => $organization->id])
            ->get(route('artifacts.pending'))->assertOk()->assertSee($artifact->title);
        $this->actingAs($approver)->withSession(['active_organization_id' => $organization->id])
            ->get(route('artifacts.pending'))->assertOk()->assertDontSee($artifact->title);
        $this->activateContext($organization, $author);
        $this->assertThrows(fn () => $workflow->decide($round, $approver, ArtifactWorkflowDecisionType::Approved, 'Aprovação prematura.'), LogicException::class);

        $forwarded = $workflow->decide($round, $reviewer, ArtifactWorkflowDecisionType::ForwardedForApproval, 'Revisão técnica concluída sem ressalvas.');
        $this->assertSame(ArtifactWorkflowState::AwaitingApproval, $forwarded->state);
        $this->actingAs($reviewer)->withSession(['active_organization_id' => $organization->id])
            ->get(route('artifacts.pending'))->assertOk()->assertDontSee($artifact->title);
        $this->actingAs($approver)->withSession(['active_organization_id' => $organization->id])
            ->get(route('artifacts.pending'))->assertOk()->assertSee($artifact->title);
        $this->activateContext($organization, $author);
        $this->assertThrows(fn () => $workflow->decide($forwarded, $reviewer, ArtifactWorkflowDecisionType::Approved, 'Sem papel de aprovação.'), LogicException::class);

        $approved = $workflow->decide($forwarded, $approver, ArtifactWorkflowDecisionType::Approved, 'Aprovado após parecer técnico.');
        $this->assertSame(ArtifactWorkflowState::Approved, $approved->state);
    }

    public function test_new_revision_after_approval_returns_to_draft_and_preserves_approval(): void
    {
        [, $actor] = $this->actor();
        $artifact = $this->artifact($actor);
        $workflow = app(ArtifactWorkflowService::class);
        $workflow->assign($artifact, $actor, DocumentRole::Author, $actor);
        $workflow->assign($artifact, $actor, DocumentRole::Reviewer, $actor);
        $workflow->assign($artifact, $actor, DocumentRole::Approver, $actor);
        $round = $workflow->submit($artifact, $actor, 'Submeter.');
        $workflow->decide($round, $actor, ArtifactWorkflowDecisionType::ForwardedForApproval, 'Revisado.');
        $workflow->decide($round, $actor, ArtifactWorkflowDecisionType::Approved, 'Aprovar.');
        $oldRevision = $artifact->revisions()->sole();

        app(ArtifactRevisionService::class)->revise($artifact, ['updated' => true], null, 1, 'Nova necessidade.', $actor);

        $this->assertSame(ArtifactWorkflowState::Draft, $artifact->fresh()->workflow_state);
        $this->assertSame($oldRevision->id, $workflow->latestApproved($artifact)->artifact_revision_id);
        $this->assertSame(2, $artifact->revisions()->count());
    }

    public function test_rounds_decisions_and_assignments_are_immutable_outside_service(): void
    {
        [, $actor] = $this->actor();
        $artifact = $this->artifact($actor);
        $workflow = app(ArtifactWorkflowService::class);
        $assignment = $workflow->assign($artifact, $actor, DocumentRole::Author, $actor);
        $round = $workflow->submit($artifact, $actor, 'Submeter.');
        $decision = $round->decisions()->sole();

        $this->assertThrows(fn () => $assignment->update(['role' => DocumentRole::Approver]), LogicException::class);
        $this->assertThrows(fn () => $round->update(['state' => ArtifactWorkflowState::Approved]), LogicException::class);
        $this->assertThrows(fn () => $decision->update(['justification' => 'Alterada']), LogicException::class);
        $this->assertThrows(fn () => $decision->delete(), LogicException::class);
        $this->assertThrows(fn () => ArtifactWorkflowDecision::query()->create($decision->getAttributes()), LogicException::class);
    }

    public function test_archived_artifact_rejects_submission(): void
    {
        [, $actor] = $this->actor();
        $artifact = $this->artifact($actor);
        $workflow = app(ArtifactWorkflowService::class);
        $workflow->assign($artifact, $actor, DocumentRole::Author, $actor);
        app(ArtifactRevisionService::class)->archive($artifact, 'Encerrado.', $actor);

        $this->assertThrows(fn () => $workflow->submit($artifact, $actor, 'Não permitido.'), LogicException::class);
    }

    public function test_inactive_and_suspended_users_cannot_receive_or_exercise_roles(): void
    {
        [$organization, $administrator] = $this->actor();
        $artifact = $this->artifact($administrator);
        $inactive = $this->member($organization);
        $inactive->update(['is_active' => false]);
        $workflow = app(ArtifactWorkflowService::class);
        $this->assertThrows(fn () => $workflow->assign($artifact, $inactive->fresh(), DocumentRole::Author, $administrator), LogicException::class);

        $suspended = $this->member($organization);
        $workflow->assign($artifact, $suspended, DocumentRole::Author, $administrator);
        OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $suspended->id)->update(['status' => OrganizationMembershipStatus::Suspended]);
        $this->assertThrows(fn () => $workflow->submit($artifact, $suspended, 'Não permitido.'), LogicException::class);
    }

    public function test_routes_render_workflow_and_apply_server_validation(): void
    {
        [$organization, $actor] = $this->actor();
        $artifact = $this->artifact($actor);
        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->get(route('artifacts.show', $artifact))->assertOk()->assertSee('Workflow da revisão atual')->assertSee('Publicações documentais');
        $this->actingAs($actor)->withSession(['active_organization_id' => $organization->id])
            ->post(route('artifacts.workflow.submit', $artifact), [])->assertSessionHasErrors('justification');
    }

    public function test_database_rejects_document_role_without_parent(): void
    {
        [$organization, $actor] = $this->actor();
        $this->expectException(QueryException::class);
        DB::table('document_role_assignments')->insert([
            'organization_id' => $organization->id, 'user_id' => $actor->id, 'role' => DocumentRole::Author->value,
            'effective_from' => now(), 'assigned_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_document_role_with_two_parents(): void
    {
        [$organization, $actor] = $this->actor();
        $initiative = app(InitiativeConfigurationService::class)->create([
            'title' => 'Pai iniciativa', 'context' => 'Contexto', 'origin' => InitiativeOrigin::Internal,
            'execution_nature' => ExecutionNature::Internal, 'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential, 'methodology' => ProjectMethodology::Kanban,
        ], $actor, 'Inicial.');
        $project = Project::factory()->create(['organization_id' => $organization->id]);

        $this->expectException(QueryException::class);
        DB::table('document_role_assignments')->insert([
            'organization_id' => $organization->id, 'initiative_id' => $initiative->id, 'project_id' => $project->id,
            'user_id' => $actor->id, 'role' => DocumentRole::Author->value, 'effective_from' => now(),
            'assigned_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function artifact(User $actor, ManagementLevel $managementLevel = ManagementLevel::Essential): Artifact
    {
        $initiative = app(InitiativeConfigurationService::class)->create([
            'title' => 'Iniciativa documental', 'context' => 'Contexto', 'origin' => InitiativeOrigin::Internal,
            'execution_nature' => ExecutionNature::Internal, 'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => $managementLevel, 'methodology' => ProjectMethodology::Kanban,
        ], $actor, 'Registro inicial.');

        return app(ArtifactRevisionService::class)->create([
            'initiative_id' => $initiative->id, 'type' => ArtifactType::InitiativeRecord, 'title' => 'Registro',
            'content' => ['field' => 'value'], 'metadata' => null, 'schema_version' => 1, 'change_reason' => 'Inicial.',
        ], $actor);
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

    private function member(Organization $organization): User
    {
        $user = User::factory()->create();
        OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_code' => OrganizationRole::Member, 'status' => OrganizationMembershipStatus::Active]);

        return $user;
    }

    private function activateContext(Organization $organization, User $user): void
    {
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
    }
}
