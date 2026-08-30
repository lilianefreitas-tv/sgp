<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Enums\TestExecutionResult;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\ProjectHomologation;
use App\Models\ProjectTestCase;
use App\Models\TestExecution;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ProjectTestingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class ProjectTestingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_project_manager_can_register_sequential_traceable_test_case(): void
    {
        [$organization, $manager, $project] = $this->scenario();

        $this->actingAs($manager)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.tests.store', $project), $this->caseData())
            ->assertRedirect();

        $this->assertDatabaseHas('project_test_cases', [
            'organization_id' => $organization->id, 'project_id' => $project->id,
            'sequence' => 1, 'code' => 'CT-001', 'status' => 'ready',
        ]);
    }

    public function test_observer_cannot_plan_or_execute_tests(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $observer = $this->projectMember($organization, $project, ProjectRole::Observer);
        $case = app(ProjectTestingService::class)->createCase($project, $this->caseData(), $manager);

        $this->actingAs($observer)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.tests.store', $project), $this->caseData())->assertForbidden();
        $this->actingAs($observer)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.tests.executions.store', [$project, $case]), $this->executionData())->assertForbidden();
    }

    public function test_tester_registers_new_immutable_execution_and_history_is_preserved(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $tester = $this->projectMember($organization, $project, ProjectRole::Tester);
        $case = app(ProjectTestingService::class)->createCase($project, $this->caseData(['assigned_tester_id' => $tester->id]), $manager);

        $this->actingAs($tester)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.tests.executions.store', [$project, $case]), $this->executionData(['result' => 'failed']))->assertRedirect();
        $this->actingAs($tester)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.tests.executions.store', [$project, $case]), $this->executionData(['result' => 'passed']))->assertRedirect();

        $this->assertDatabaseHas('test_executions', ['test_case_id' => $case->id, 'execution_number' => 1, 'result' => 'failed']);
        $this->assertDatabaseHas('test_executions', ['test_case_id' => $case->id, 'execution_number' => 2, 'result' => 'passed']);
        $execution = TestExecution::query()->where('test_case_id', $case->id)->firstOrFail();
        $this->expectException(LogicException::class);
        $execution->update(['notes' => 'Tentativa de reescrita']);
    }

    public function test_only_designated_tester_can_execute_case(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $designated = $this->projectMember($organization, $project, ProjectRole::Tester);
        $other = $this->projectMember($organization, $project, ProjectRole::Tester);
        $case = app(ProjectTestingService::class)->createCase($project, $this->caseData(['assigned_tester_id' => $designated->id]), $manager);

        $this->actingAs($other)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.tests.executions.store', [$project, $case]), $this->executionData())
            ->assertSessionHasErrors('executor');
    }

    public function test_draft_case_cannot_be_executed(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $tester = $this->projectMember($organization, $project, ProjectRole::Tester);
        $case = app(ProjectTestingService::class)->createCase($project, $this->caseData(['status' => 'draft']), $manager);

        $this->actingAs($tester)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.tests.executions.store', [$project, $case]), $this->executionData())
            ->assertSessionHasErrors('status');
    }

    public function test_evidence_is_private_hashed_audited_and_downloadable_by_project_member(): void
    {
        Storage::fake('local');
        [$organization, $manager, $project] = $this->scenario();
        $tester = $this->projectMember($organization, $project, ProjectRole::Tester);
        $case = app(ProjectTestingService::class)->createCase($project, $this->caseData(['assigned_tester_id' => $tester->id]), $manager);
        $execution = app(ProjectTestingService::class)->execute($case, $this->executionData(), $tester);

        $this->actingAs($tester)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.tests.evidences.store', [$project, $case, $execution]), [
                'file' => UploadedFile::fake()->createWithContent('evidencia.pdf', "%PDF-1.4\n%%EOF"),
                'description' => 'Captura da execução.',
            ])->assertRedirect();

        $evidence = $execution->evidences()->firstOrFail();
        Storage::disk('local')->assertExists($evidence->path);
        $this->assertSame(hash('sha256', "%PDF-1.4\n%%EOF"), $evidence->sha256);
        $this->actingAs($manager)->withSession(['active_organization_id' => $organization->id])
            ->get(route('projects.tests.evidences.download', [$project, $case, $execution, $evidence]))->assertOk();
        $this->assertDatabaseHas('organization_audit_events', ['action' => 'test.evidence.download', 'result' => 'success']);
    }

    public function test_missing_evidence_returns_controlled_not_found_and_audit(): void
    {
        Storage::fake('local');
        [$organization, $manager, $project] = $this->scenario();
        $tester = $this->projectMember($organization, $project, ProjectRole::Tester);
        $case = app(ProjectTestingService::class)->createCase($project, $this->caseData(), $manager);
        $execution = app(ProjectTestingService::class)->execute($case, $this->executionData(), $tester);
        $evidence = $execution->evidences()->create([
            'uploaded_by' => $tester->id, 'disk' => 'local', 'path' => 'missing/evidence.pdf',
            'original_name' => 'evidence.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20,
            'sha256' => str_repeat('a', 64),
        ]);

        $this->actingAs($manager)->withSession(['active_organization_id' => $organization->id])
            ->get(route('projects.tests.evidences.download', [$project, $case, $execution, $evidence]))->assertNotFound();
        $this->assertDatabaseHas('organization_audit_events', ['action' => 'test.evidence.download', 'result' => 'failed']);
    }

    public function test_validator_can_approve_only_when_all_ready_cases_passed_with_evidence(): void
    {
        Storage::fake('local');
        [$organization, $manager, $project] = $this->scenario();
        $tester = $this->projectMember($organization, $project, ProjectRole::Tester);
        $validator = $this->projectMember($organization, $project, ProjectRole::Validator);
        $case = app(ProjectTestingService::class)->createCase($project, $this->caseData(), $manager);
        $execution = app(ProjectTestingService::class)->execute($case, $this->executionData(), $tester);
        $execution->evidences()->create([
            'uploaded_by' => $tester->id, 'disk' => 'local', 'path' => 'proof.pdf', 'original_name' => 'proof.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => str_repeat('b', 64),
        ]);

        $this->actingAs($validator)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.homologations.store', $project), $this->homologationData())
            ->assertRedirect();
        $this->assertDatabaseHas('project_homologations', ['project_id' => $project->id, 'code' => 'HOM-001', 'status' => 'approved']);
    }

    public function test_approval_is_blocked_when_case_has_no_execution_or_evidence(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $validator = $this->projectMember($organization, $project, ProjectRole::Validator);
        app(ProjectTestingService::class)->createCase($project, $this->caseData(), $manager);

        $this->actingAs($validator)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.homologations.store', $project), $this->homologationData())
            ->assertSessionHasErrors('status');
    }

    public function test_critical_failure_cannot_be_approved_with_reservations(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $tester = $this->projectMember($organization, $project, ProjectRole::Tester);
        $validator = $this->projectMember($organization, $project, ProjectRole::Validator);
        $case = app(ProjectTestingService::class)->createCase($project, $this->caseData(['severity' => 'critical']), $manager);
        app(ProjectTestingService::class)->execute($case, $this->executionData(['result' => 'failed']), $tester);

        $this->actingAs($validator)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.homologations.store', $project), $this->homologationData(['status' => 'approved_with_reservations']))
            ->assertSessionHasErrors('status');
    }

    public function test_administrator_without_validator_role_cannot_record_business_decision(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        app(ProjectTestingService::class)->createCase($project, $this->caseData(), $manager);

        $this->actingAs($manager)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.homologations.store', $project), $this->homologationData())->assertForbidden();
    }

    private function caseData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Validar fluxo principal', 'objective' => 'Confirmar o comportamento previsto.',
            'preconditions' => 'Projeto preparado.', 'test_data' => 'Massa controlada.',
            'steps' => "1. Abrir o projeto.\n2. Executar a ação.",
            'expected_result' => 'A operação é concluída e auditada.',
            'severity' => 'high', 'status' => 'ready', 'assigned_tester_id' => null,
            'requirement_id' => null, 'change_request_id' => null, 'baseline_id' => null,
        ], $overrides);
    }

    private function executionData(array $overrides = []): array
    {
        return array_merge([
            'result' => TestExecutionResult::Passed->value, 'environment' => 'Homologação',
            'observed_result' => 'Resultado correspondente ao esperado.', 'notes' => null, 'defect_reference' => null,
        ], $overrides);
    }

    private function homologationData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Homologação funcional', 'status' => 'approved', 'baseline_id' => null,
            'commit_reference' => 'abcdef1', 'environment' => 'Homologação',
            'scope' => 'Fluxos do pacote corrente.', 'decision_notes' => 'Critérios atendidos e evidências verificadas.',
        ], $overrides);
    }

    private function projectMember(Organization $organization, Project $project, ProjectRole $role): User
    {
        $user = User::factory()->create();
        OrganizationMembership::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $user->id,
            'role_code' => OrganizationRole::Member, 'status' => OrganizationMembershipStatus::Active,
        ]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'started_at' => today()]);
        return $user;
    }

    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->create();
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $manager->id,
            'role_code' => OrganizationRole::Administrator, 'status' => OrganizationMembershipStatus::Active,
        ]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
        $project = Project::factory()->create(['manager_id' => $manager->id]);
        $project->memberships()->create(['user_id' => $manager->id, 'role' => ProjectRole::ProjectManager, 'is_active' => true, 'started_at' => today()]);
        return [$organization, $manager, $project];
    }
}
