<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestBaselineDisposition;
use App\Enums\ChangeRequestClassification;
use App\Enums\ChangeRequestContractDisposition;
use App\Enums\ChangeRequestImplementationStatus;
use App\Enums\ChangeRequestOrigin;
use App\Enums\ChangeRequestRecommendation;
use App\Enums\ChangeRequestRiskLevel;
use App\Enums\ChangeRequestState;
use App\Enums\ChangeRequestUrgency;
use App\Enums\ContractEntryMode;
use App\Enums\ContractStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Models\ChangeRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Services\ChangeRequestImpactAnalysisService;
use App\Services\ChangeRequestService;
use App\Services\OrganizationContext;
use App\Services\ProjectContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class ChangeRequestImplementationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_manager_completes_implementation_with_evidence_and_new_traceable_baseline(): void
    {
        Storage::fake('local');
        [$organization, $manager, $project] = $this->scenario();
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'title' => 'Regra afetada pela mudança',
            'is_active' => true,
        ]);
        $changeRequest = $this->approvedRequest($project, $manager);

        $this->attachEvidence($organization, $manager, $project, $changeRequest);
        $payload = $this->implementationData($manager, [
            'baseline_disposition' => ChangeRequestBaselineDisposition::CreateNew->value,
            'baseline_title' => 'Baseline após '.$changeRequest->code,
        ]);

        $this->actingAs($manager)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.implementation.start', [$project, $changeRequest]), $payload)
            ->assertRedirect();

        $this->post(route('projects.change-requests.implementation.complete', [$project, $changeRequest]), $payload + [
            'execution_summary' => 'Mudança implantada conforme o plano aprovado.',
            'verification_summary' => 'Testes automatizados e validação funcional concluídos com sucesso.',
        ])->assertRedirect();

        $implementation = $changeRequest->implementation()->firstOrFail();
        $baseline = $implementation->newBaseline()->firstOrFail();

        $this->assertSame(ChangeRequestImplementationStatus::Completed, $implementation->status);
        $this->assertSame(ChangeRequestState::Implemented, $changeRequest->fresh()->state);
        $this->assertSame($changeRequest->id, $baseline->source_change_request_id);
        $this->assertSame($organization->id, $baseline->organization_id);
        $this->assertDatabaseHas('project_baseline_items', [
            'project_baseline_id' => $baseline->id,
            'item_type' => 'requirement',
            'source_id' => $requirement->id,
        ]);
        $this->assertDatabaseHas('change_request_transitions', [
            'change_request_id' => $changeRequest->id,
            'from_state' => ChangeRequestState::Approved->value,
            'to_state' => ChangeRequestState::Implemented->value,
            'actor_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('change_request_implementation_events', [
            'implementation_id' => $implementation->id,
            'event_type' => 'implementation_completed',
            'actor_id' => $manager->id,
        ]);

        try {
            $baseline->update(['title' => 'Tentativa de alteração']);
            $this->fail('Uma baseline constituída não pode ser alterada.');
        } catch (LogicException) {
            $this->assertSame('Baseline após '.$changeRequest->code, $baseline->fresh()->title);
        }

        $this->expectException(LogicException::class);
        $implementation->events()->where('event_type', 'implementation_completed')->firstOrFail()
            ->update(['event_type' => 'altered']);
    }

    public function test_assigned_project_member_can_save_and_start_but_cannot_complete(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $executor = $this->projectMember($organization, $project, ProjectRole::Developer);
        $changeRequest = $this->approvedRequest($project, $manager);
        $payload = $this->implementationData($executor);

        $this->actingAs($manager)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.implementation.update', [$project, $changeRequest]), $payload)
            ->assertRedirect();

        $this->actingAs($executor)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.implementation.start', [$project, $changeRequest]), $payload)
            ->assertRedirect();

        $this->assertSame(
            ChangeRequestImplementationStatus::InProgress,
            $changeRequest->implementation()->firstOrFail()->status,
        );

        $this->post(route('projects.change-requests.implementation.complete', [$project, $changeRequest]), $payload + [
            'execution_summary' => 'Execução registrada pelo responsável.',
            'verification_summary' => 'Verificação registrada pelo responsável.',
        ])->assertForbidden();

        $this->assertSame(ChangeRequestState::Approved, $changeRequest->fresh()->state);
    }

    public function test_completion_requires_evidence_and_formal_resolution_of_amendment(): void
    {
        Storage::fake('local');
        [$organization, $manager, $project] = $this->scenario();
        $changeRequest = $this->approvedRequest($project, $manager);
        $contract = app(ProjectContractService::class)->create([
            'project_id' => $project->id,
            'initiative_id' => null,
            'title' => 'Contrato principal',
            'contract_kind' => 'other',
            'entry_mode' => ContractEntryMode::Editor->value,
            'status' => ContractStatus::Active->value,
            'content' => '<p>Objeto contratual.</p>',
            'reason' => 'Registro inicial.',
        ], $manager);
        $payload = $this->implementationData($manager, [
            'contract_disposition' => ChangeRequestContractDisposition::AmendmentRequired->value,
            'contract_id' => $contract->id,
        ]);

        $this->actingAs($manager)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.implementation.start', [$project, $changeRequest]), $payload)
            ->assertRedirect();

        $this->attachEvidence($organization, $manager, $project, $changeRequest);
        $this->post(route('projects.change-requests.implementation.complete', [$project, $changeRequest]), $payload + [
            'execution_summary' => 'Implementação técnica encerrada.',
            'verification_summary' => 'Evidência conferida.',
        ])->assertSessionHasErrors('contract_disposition');

        $this->assertSame(ChangeRequestState::Approved, $changeRequest->fresh()->state);
        $this->assertSame(
            ChangeRequestImplementationStatus::InProgress,
            $changeRequest->implementation()->firstOrFail()->status,
        );
    }

    public function test_formalized_amendment_creates_contract_version_linked_to_change_request(): void
    {
        Storage::fake('local');
        [$organization, $manager, $project] = $this->scenario();
        $changeRequest = $this->approvedRequest($project, $manager);
        $contract = app(ProjectContractService::class)->create([
            'project_id' => $project->id,
            'initiative_id' => null,
            'title' => 'Contrato com aditivo',
            'contract_kind' => 'other',
            'entry_mode' => ContractEntryMode::Hybrid->value,
            'status' => ContractStatus::Active->value,
            'content' => '<p>Contrato vigente.</p>',
            'reason' => 'Versão inicial.',
        ], $manager);
        $this->attachEvidence($organization, $manager, $project, $changeRequest);
        $payload = $this->implementationData($manager, [
            'contract_disposition' => ChangeRequestContractDisposition::AmendmentRegistered->value,
            'contract_id' => $contract->id,
            'amendment_reference' => 'AD-001/2026',
            'amendment_summary' => 'Ajuste formal de escopo e prazo decorrente da solicitação.',
            'amendment_effective_date' => now()->toDateString(),
            'baseline_disposition' => ChangeRequestBaselineDisposition::NotRequired->value,
            'baseline_title' => null,
            'baseline_justification' => 'Não há item de configuração alterado nesta execução.',
        ]);

        $this->actingAs($manager)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.implementation.start', [$project, $changeRequest]), $payload)
            ->assertRedirect();
        $this->post(route('projects.change-requests.implementation.complete', [$project, $changeRequest]), $payload + [
            'execution_summary' => 'Aditivo formalizado e mudança aplicada.',
            'verification_summary' => 'Referência contratual e entrega conferidas.',
        ])->assertRedirect();

        $implementation = $changeRequest->implementation()->firstOrFail();
        $version = $contract->versions()->where('version', 2)->firstOrFail();

        $this->assertSame(2, $implementation->amendment_contract_version);
        $this->assertSame($changeRequest->id, $version->snapshot['amendment']['change_request_id']);
        $this->assertSame('AD-001/2026', $version->snapshot['amendment']['reference']);
        $this->assertNull($implementation->new_baseline_id);
    }

    public function test_another_organization_cannot_create_or_change_implementation(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $changeRequest = $this->approvedRequest($project, $manager);

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
            ->post("/projects/{$project->id}/change-requests/{$changeRequest->id}/implementation", [
                'contract_disposition' => ChangeRequestContractDisposition::NotApplicable->value,
                'baseline_disposition' => ChangeRequestBaselineDisposition::CreateNew->value,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('change_request_implementations', [
            'change_request_id' => $changeRequest->id,
        ]);
        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'organization_id' => $organization->id,
            'state' => ChangeRequestState::Approved->value,
        ]);
    }

    private function approvedRequest(Project $project, User $manager): ChangeRequest
    {
        $service = app(ChangeRequestService::class);
        $changeRequest = $service->create($project, [
            'title' => 'Implementar mudança aprovada',
            'origin' => ChangeRequestOrigin::InternalTeam->value,
            'description' => 'A solução aprovada deve ser implantada com rastreabilidade.',
            'justification' => 'Atender ao parecer técnico e preservar a configuração.',
            'urgency' => ChangeRequestUrgency::Medium->value,
        ], $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::Submitted, $manager);
        $changeRequest = $service->transition($changeRequest, ChangeRequestState::UnderAnalysis, $manager);
        app(ChangeRequestImpactAnalysisService::class)->complete($changeRequest, [
            'classification' => ChangeRequestClassification::ScopeChange->value,
            'risk_level' => ChangeRequestRiskLevel::Medium->value,
            'recommendation' => ChangeRequestRecommendation::Approve->value,
            'executive_summary' => 'Mudança viável e controlada.',
            'scope_impact' => 'Altera uma entrega aprovada.',
            'requirements_impact' => 'Atualiza requisito relacionado.',
            'technical_impact' => 'Exige ajuste técnico controlado.',
            'data_impact' => 'Não aplicável, sem alteração de dados.',
            'security_impact' => 'Mantém o isolamento organizacional.',
            'schedule_impact' => 'Impacto de dois dias.',
            'resources_impact' => 'Equipe atual é suficiente.',
            'cost_impact' => 'Sem custo adicional.',
            'contract_impact' => 'Avaliado conforme cenário de teste.',
            'quality_impact' => 'Exige regressão funcional.',
            'testing_impact' => 'Testes de fluxo e autorização.',
            'operations_impact' => 'Implantação em janela planejada.',
            'documentation_impact' => 'Atualiza baseline e histórico.',
            'risks_and_mitigations' => 'Regressão mitigada por testes.',
            'estimated_effort_hours' => 16,
            'estimated_schedule_days' => 2,
            'estimated_cost_amount' => 0,
        ], $manager);

        return $service->transition(
            $changeRequest,
            ChangeRequestState::Approved,
            $manager,
            'Aprovada para implementação controlada.',
        );
    }

    /** @param array<string, mixed> $overrides */
    private function implementationData(User $responsible, array $overrides = []): array
    {
        return array_replace([
            'responsible_id' => $responsible->id,
            'plan_summary' => 'Planejar, implementar, testar, anexar evidências e atualizar a configuração.',
            'planned_start_date' => now()->toDateString(),
            'target_completion_date' => now()->addDays(5)->toDateString(),
            'contract_disposition' => ChangeRequestContractDisposition::NotApplicable->value,
            'contract_id' => null,
            'contract_justification' => 'Mudança interna sem instrumento contratual relacionado.',
            'amendment_reference' => null,
            'amendment_summary' => null,
            'amendment_effective_date' => null,
            'baseline_disposition' => ChangeRequestBaselineDisposition::CreateNew->value,
            'baseline_title' => 'Baseline pós-implementação',
            'baseline_justification' => 'Constituir nova referência após a mudança aprovada.',
        ], $overrides);
    }

    private function attachEvidence(
        Organization $organization,
        User $actor,
        Project $project,
        ChangeRequest $changeRequest,
    ): void {
        $this->actingAs($actor)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.change-requests.attachments.store', [$project, $changeRequest]), [
                'attachment_kind' => 'evidence',
                'description' => 'Evidência da implementação.',
                'file' => UploadedFile::fake()->createWithContent(
                    'evidencia-p07-3.pdf',
                    "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
                ),
            ])
            ->assertRedirect();
    }

    private function projectMember(
        Organization $organization,
        Project $project,
        ProjectRole $role,
    ): User {
        $user = User::factory()->create();
        OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_code' => OrganizationRole::Member,
            'status' => OrganizationMembershipStatus::Active,
        ]);
        $project->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
            'started_at' => today(),
        ]);

        return $user;
    }

    /** @return array{Organization, User, Project} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->create();
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $manager->id,
            'role_code' => OrganizationRole::Administrator,
            'status' => OrganizationMembershipStatus::Active,
        ]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
        $project = Project::factory()->create(['manager_id' => $manager->id]);
        $project->memberships()->create([
            'user_id' => $manager->id,
            'role' => ProjectRole::ProjectManager,
            'is_active' => true,
            'started_at' => today(),
        ]);

        return [$organization, $manager, $project];
    }
}
