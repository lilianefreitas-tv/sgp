<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestOrigin;
use App\Enums\ChangeRequestState;
use App\Enums\ChangeRequestUrgency;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Models\ChangeRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Services\ChangeRequestService;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class ChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_user_can_create_minimal_draft_and_complete_it_progressively(): void
    {
        [$organization, $actor, $project] = $this->scenario();
        $service = app(ChangeRequestService::class);

        $changeRequest = $service->create($project, [
            'title' => 'Ajustar regra de aprovação',
            'origin' => ChangeRequestOrigin::Requester->value,
        ], $actor);

        $this->assertSame('RM-001', $changeRequest->code);
        $this->assertSame(ChangeRequestState::Draft, $changeRequest->state);
        $this->assertSame($organization->id, $changeRequest->organization_id);
        $this->assertSame($actor->id, $changeRequest->requester_id);
        $this->assertCount(1, $changeRequest->transitions);

        $updated = $service->update($changeRequest, [
            'title' => 'Ajustar regra de aprovação',
            'origin' => ChangeRequestOrigin::Requester->value,
            'description' => 'A aprovação atual não contempla a nova alçada.',
            'justification' => 'Preservar a segregação de funções.',
            'urgency' => ChangeRequestUrgency::High->value,
        ], $actor);
        $submitted = $service->transition($updated, ChangeRequestState::Submitted, $actor);

        $this->assertSame(ChangeRequestState::Submitted, $submitted->state);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertCount(2, $submitted->transitions);
    }

    public function test_submission_requires_complete_initial_information(): void
    {
        [, $actor, $project] = $this->scenario();
        $changeRequest = app(ChangeRequestService::class)->create($project, [
            'title' => 'Rascunho simples',
            'origin' => ChangeRequestOrigin::InternalTeam->value,
        ], $actor);

        $this->expectException(ValidationException::class);
        app(ChangeRequestService::class)->transition($changeRequest, ChangeRequestState::Submitted, $actor);
    }

    public function test_initial_workflow_records_immutable_transitions_and_blocks_invalid_decision(): void
    {
        [, $actor, $project] = $this->scenario();
        $service = app(ChangeRequestService::class);
        $changeRequest = $service->create($project, $this->completeData(), $actor);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Submitted, $actor);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::UnderAnalysis, $actor);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Returned, $actor, 'Detalhar o cenário de exceção.');
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Submitted, $actor);

        $this->assertSame(ChangeRequestState::Submitted, $changeRequest->state);
        $this->assertCount(5, $changeRequest->transitions);
        $this->assertDatabaseHas('change_request_transitions', [
            'change_request_id' => $changeRequest->id,
            'from_state' => ChangeRequestState::UnderAnalysis->value,
            'to_state' => ChangeRequestState::Returned->value,
            'reason' => 'Detalhar o cenário de exceção.',
        ]);

        try {
            $service->transition($changeRequest, ChangeRequestState::Approved, $actor);
            $this->fail('Uma solicitação submetida não pode ser aprovada antes do início da análise.');
        } catch (ValidationException) {
            $this->assertSame(ChangeRequestState::Submitted, $changeRequest->fresh()->state);
        }

        $this->expectException(LogicException::class);
        $changeRequest->transitions()->firstOrFail()->update(['reason' => 'Tentativa de alteração']);
    }

    public function test_project_manager_can_approve_request_under_analysis_with_formal_opinion(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $service = app(ChangeRequestService::class);
        $changeRequest = $service->create($project, $this->completeData(), $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Submitted, $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::UnderAnalysis, $manager);

        $this->actingAs($manager)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.approve', [$project, $changeRequest]), [
                'reason' => 'Mudança viável e alinhada aos objetivos do projeto.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'state' => ChangeRequestState::Approved->value,
        ]);
        $this->assertDatabaseHas('change_request_transitions', [
            'change_request_id' => $changeRequest->id,
            'from_state' => ChangeRequestState::UnderAnalysis->value,
            'to_state' => ChangeRequestState::Approved->value,
            'actor_id' => $manager->id,
            'reason' => 'Mudança viável e alinhada aos objetivos do projeto.',
        ]);
    }

    public function test_rejection_requires_reason_and_is_recorded_in_history(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $service = app(ChangeRequestService::class);
        $changeRequest = $service->create($project, $this->completeData(), $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Submitted, $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::UnderAnalysis, $manager);

        $this->actingAs($manager)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.reject', [$project, $changeRequest]))
            ->assertSessionHasErrors('reason');

        $this->assertSame(ChangeRequestState::UnderAnalysis, $changeRequest->fresh()->state);

        $this->post(route('projects.change-requests.reject', [$project, $changeRequest]), [
            'reason' => 'Impacto incompatível com o objetivo vigente do projeto.',
        ])->assertRedirect();

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'state' => ChangeRequestState::Rejected->value,
        ]);
        $this->assertDatabaseHas('change_request_transitions', [
            'change_request_id' => $changeRequest->id,
            'to_state' => ChangeRequestState::Rejected->value,
            'actor_id' => $manager->id,
            'reason' => 'Impacto incompatível com o objetivo vigente do projeto.',
        ]);
    }

    public function test_affected_items_and_baseline_must_belong_to_the_same_project(): void
    {
        [, $actor, $project] = $this->scenario();
        $otherProject = Project::factory()->create(['manager_id' => $actor->id]);
        $foreignRequirement = Requirement::factory()->create(['project_id' => $otherProject->id]);

        $this->expectException(ValidationException::class);
        app(ChangeRequestService::class)->create($project, $this->completeData() + [
            'affected' => ['requirement' => [$foreignRequirement->id]],
        ], $actor);
    }

    public function test_user_from_another_organization_cannot_discover_the_request(): void
    {
        [$organization, $actor, $project] = $this->scenario();
        $changeRequest = app(ChangeRequestService::class)->create($project, $this->completeData(), $actor);

        app(OrganizationContext::class)->clear();
        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create();
        OrganizationMembership::factory()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherUser->id,
            'role_code' => OrganizationRole::Administrator,
            'status' => OrganizationMembershipStatus::Active,
        ]);

        $this->actingAs($otherUser)
            ->withSession(['active_organization_id' => $otherOrganization->id])
            ->get("/projects/{$project->id}/change-requests/{$changeRequest->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_private_evidence_upload_and_download_respect_project_authorization(): void
    {
        Storage::fake('local');
        [$organization, $actor, $project] = $this->scenario();
        $changeRequest = app(ChangeRequestService::class)->create($project, $this->completeData(), $actor);

        $this->actingAs($actor)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.attachments.store', [$project, $changeRequest]), [
                'attachment_kind' => 'evidence',
                'description' => 'Registro comprobatório.',
                'file' => UploadedFile::fake()->createWithContent(
                    'evidencia.pdf',
                    "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
                ),
            ])
            ->assertRedirect();

        $attachment = $changeRequest->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('evidence', $attachment->attachment_kind);
        $this->assertSame($organization->id, $attachment->organization_id);

        $this->actingAs($actor)
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('projects.change-requests.attachments.download', [$project, $changeRequest, $attachment]))
            ->assertOk();
    }

    public function test_requirements_analyst_can_start_analysis_and_requester_can_cancel(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $analyst = User::factory()->create();
        OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $analyst->id,
            'role_code' => OrganizationRole::Member,
            'status' => OrganizationMembershipStatus::Active,
        ]);
        $project->memberships()->create([
            'user_id' => $analyst->id,
            'role' => ProjectRole::RequirementsAnalyst,
            'is_active' => true,
            'started_at' => today(),
        ]);

        $service = app(ChangeRequestService::class);
        $changeRequest = $service->create($project, $this->completeData(), $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Submitted, $manager);

        $this->actingAs($analyst)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.start-analysis', [$project, $changeRequest]), [
                'analyst_id' => $analyst->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'state' => ChangeRequestState::UnderAnalysis->value,
            'analyst_id' => $analyst->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function completeData(): array
    {
        return [
            'title' => 'Revisar regra de aprovação',
            'origin' => ChangeRequestOrigin::InternalTeam->value,
            'description' => 'A regra atual precisa ser ajustada.',
            'justification' => 'Adequar o fluxo à governança aprovada.',
            'urgency' => ChangeRequestUrgency::Medium->value,
        ];
    }

    private function scenario(): array
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
        $project = Project::factory()->create(['manager_id' => $actor->id]);
        $project->memberships()->create([
            'user_id' => $actor->id,
            'role' => ProjectRole::ProjectManager,
            'is_active' => true,
            'started_at' => today(),
        ]);

        return [$organization, $actor, $project];
    }
}
