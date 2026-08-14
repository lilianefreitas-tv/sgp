<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestAnalysisStatus;
use App\Enums\ChangeRequestClassification;
use App\Enums\ChangeRequestOrigin;
use App\Enums\ChangeRequestRecommendation;
use App\Enums\ChangeRequestRiskLevel;
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
use App\Services\ChangeRequestImpactAnalysisService;
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
                'reason' => 'Tentativa sem análise concluída.',
            ])
            ->assertForbidden();

        $this->completeImpactAnalysis($changeRequest, $manager, ChangeRequestRecommendation::Approve);

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
        $this->completeImpactAnalysis($changeRequest, $manager, ChangeRequestRecommendation::Reject);

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

    public function test_impact_analysis_can_be_saved_progressively_and_is_immutable_after_completion(): void
    {
        [, $manager, $project] = $this->scenario();
        $changeRequest = app(ChangeRequestService::class)->create($project, $this->completeData(), $manager);
        $changeRequest = app(ChangeRequestService::class)->transition($changeRequest, ChangeRequestState::Submitted, $manager);
        $changeRequest = app(ChangeRequestService::class)->transition($changeRequest, ChangeRequestState::UnderAnalysis, $manager);
        $analysisService = app(ChangeRequestImpactAnalysisService::class);

        $draft = $analysisService->saveDraft($changeRequest, [
            'executive_summary' => 'Avaliação iniciada.',
            'classification' => ChangeRequestClassification::ScopeChange->value,
        ], $manager);

        $this->assertSame(ChangeRequestAnalysisStatus::Draft, $draft->status);
        $this->assertSame('Avaliação iniciada.', $draft->executive_summary);

        try {
            $analysisService->complete($changeRequest, [
                'executive_summary' => 'Ainda incompleta.',
            ], $manager);
            $this->fail('Uma análise incompleta não pode ser concluída.');
        } catch (ValidationException) {
            $this->assertSame(ChangeRequestAnalysisStatus::Draft, $draft->fresh()->status);
        }

        $completed = $analysisService->complete(
            $changeRequest,
            $this->completeImpactData(ChangeRequestRecommendation::Approve),
            $manager,
        );

        $this->assertSame(ChangeRequestAnalysisStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);

        $this->expectException(LogicException::class);
        $completed->update(['executive_summary' => 'Tentativa de alteração.']);
    }

    public function test_new_analysis_round_preserves_completed_previous_round(): void
    {
        [, $manager, $project] = $this->scenario();
        $service = app(ChangeRequestService::class);
        $changeRequest = $service->create($project, $this->completeData(), $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Submitted, $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::UnderAnalysis, $manager);
        $this->completeImpactAnalysis($changeRequest, $manager, ChangeRequestRecommendation::ReturnForAdjustment);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Returned, $manager, 'Ajustar os requisitos afetados.');
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Submitted, $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::UnderAnalysis, $manager);

        $analyses = $changeRequest->impactAnalyses()->orderBy('round')->get();
        $this->assertCount(2, $analyses);
        $this->assertSame(ChangeRequestAnalysisStatus::Completed, $analyses[0]->status);
        $this->assertSame(ChangeRequestAnalysisStatus::Draft, $analyses[1]->status);
        $this->assertSame(1, $analyses[0]->round);
        $this->assertSame(2, $analyses[1]->round);
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

    public function test_assigned_requirements_analyst_can_complete_analysis_but_cannot_decide(): void
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
        $this->assertDatabaseHas('change_request_impact_analyses', [
            'change_request_id' => $changeRequest->id,
            'round' => 1,
            'analyst_id' => $analyst->id,
            'status' => ChangeRequestAnalysisStatus::Draft->value,
        ]);

        $changeRequest = $changeRequest->fresh();
        $this->actingAs($analyst)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.impact-analysis.complete', [$project, $changeRequest]),
                $this->completeImpactData(ChangeRequestRecommendation::Approve))
            ->assertRedirect();

        $this->assertDatabaseHas('change_request_impact_analyses', [
            'change_request_id' => $changeRequest->id,
            'round' => 1,
            'completed_by' => $analyst->id,
            'status' => ChangeRequestAnalysisStatus::Completed->value,
        ]);

        $this->post(route('projects.change-requests.approve', [$project, $changeRequest]), [
            'reason' => 'Analista não pode decidir.',
        ])->assertForbidden();
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

    private function completeImpactAnalysis(
        ChangeRequest $changeRequest,
        User $actor,
        ChangeRequestRecommendation $recommendation,
    ): void {
        app(ChangeRequestImpactAnalysisService::class)->complete(
            $changeRequest,
            $this->completeImpactData($recommendation),
            $actor,
        );
    }

    /** @return array<string, mixed> */
    private function completeImpactData(ChangeRequestRecommendation $recommendation): array
    {
        return [
            'classification' => ChangeRequestClassification::ScopeChange->value,
            'risk_level' => ChangeRequestRiskLevel::Medium->value,
            'recommendation' => $recommendation->value,
            'executive_summary' => 'Mudança tecnicamente viável com impactos controláveis.',
            'scope_impact' => 'Altera uma entrega prevista sem mudar o objetivo principal.',
            'requirements_impact' => 'Exige revisão dos requisitos e critérios de aceite associados.',
            'technical_impact' => 'Exige ajuste no serviço e na interface relacionados.',
            'data_impact' => 'Não aplicável, sem alteração de estrutura ou migração de dados.',
            'security_impact' => 'Mantém as permissões e o isolamento organizacional vigentes.',
            'schedule_impact' => 'Acrescenta dois dias ao cronograma planejado.',
            'resources_impact' => 'Utiliza a equipe atual sem necessidade de nova especialidade.',
            'cost_impact' => 'Absorvido pelo orçamento vigente do projeto.',
            'contract_impact' => 'Não aplicável, dentro do escopo contratual vigente.',
            'quality_impact' => 'Exige regressão do fluxo alterado.',
            'testing_impact' => 'Inclui testes de fluxo, autorização e isolamento.',
            'operations_impact' => 'Implantação seguirá a janela operacional existente.',
            'documentation_impact' => 'Atualiza especificação, matriz e registro de mudança.',
            'risks_and_mitigations' => 'Risco de regressão mitigado por testes automatizados.',
            'estimated_effort_hours' => 16,
            'estimated_schedule_days' => 2,
            'estimated_cost_amount' => 0,
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
