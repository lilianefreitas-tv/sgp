<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ProjectTestingService;
use App\Services\ProjectTraceabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_summary_calculates_complete_requirement_test_execution_and_evidence_coverage(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $tester = $this->projectMember($organization, $project, ProjectRole::Tester);
        $requirement = Requirement::factory()->create(['project_id' => $project->id, 'is_active' => true]);
        Task::factory()->create(['project_id' => $project->id, 'requirement_id' => $requirement->id, 'is_active' => true]);
        $case = app(ProjectTestingService::class)->createCase($project, $this->caseData($requirement->id), $manager);
        $execution = app(ProjectTestingService::class)->execute($case, $this->executionData(), $tester);
        $execution->evidences()->create([
            'uploaded_by' => $tester->id, 'disk' => 'local', 'path' => 'proof.pdf', 'original_name' => 'proof.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => str_repeat('a', 64),
        ]);

        $summary = app(ProjectTraceabilityService::class)->summary($project);

        $this->assertSame(100, $summary['requirement_work_coverage']);
        $this->assertSame(100, $summary['requirement_test_coverage']);
        $this->assertSame(100, $summary['execution_coverage']);
        $this->assertSame(100, $summary['evidence_coverage']);
        $this->assertSame(0, $summary['gap_count']);
    }

    public function test_matrix_exposes_named_gaps_without_creating_parallel_records(): void
    {
        [, , $project] = $this->scenario();
        Requirement::factory()->create(['project_id' => $project->id, 'is_active' => true]);

        $matrix = app(ProjectTraceabilityService::class)->matrix($project);

        $this->assertSame(2, $matrix['summary']['gap_count']);
        $this->assertSame(['Sem tarefa vinculada', 'Sem caso de teste'], $matrix['requirements']->first()['gaps']->values()->all());
    }

    public function test_global_traceability_lists_only_projects_visible_in_active_organization(): void
    {
        [$organization, $member, $project, $membership] = $this->scenario(OrganizationRole::Member);
        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create();
        $otherMembership = OrganizationMembership::factory()->create([
            'organization_id' => $otherOrganization->id, 'user_id' => $otherUser->id,
            'role_code' => OrganizationRole::Administrator, 'status' => OrganizationMembershipStatus::Active,
        ]);
        app(OrganizationContext::class)->activate($otherMembership, collect([$otherMembership]));
        $otherProject = Project::factory()->create(['name' => 'Projeto de outra organização']);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));

        $this->actingAs($member)->withSession(['active_organization_id' => $organization->id])
            ->get(route('traceability.index'))->assertOk()->assertSee($project->name)->assertDontSee($otherProject->name);
    }

    public function test_project_member_can_open_traceability_and_follow_requirement_link(): void
    {
        [$organization, $member, $project] = $this->scenario(OrganizationRole::Member);
        $requirement = Requirement::factory()->create(['project_id' => $project->id, 'title' => 'Autenticação rastreável']);

        $this->actingAs($member)->withSession(['active_organization_id' => $organization->id])
            ->get(route('projects.traceability.show', $project))
            ->assertOk()->assertSee('Autenticação rastreável')->assertSee(route('projects.requirements.show', [$project, $requirement]), false);
    }

    public function test_mps_support_view_states_limits_and_never_promises_certification(): void
    {
        [$organization, $member, $project] = $this->scenario(OrganizationRole::Member);

        $this->actingAs($member)->withSession(['active_organization_id' => $organization->id])
            ->get(route('projects.traceability.show', $project))->assertOk()
            ->assertSee('Matriz de apoio ao MPS.BR')
            ->assertSee('não certifica a organização')
            ->assertSeeInOrder(['GPR', 'REQ', 'GCO', 'VV', 'GDE', 'MED', 'GPC', 'AQU']);
    }

    private function caseData(int $requirementId): array
    {
        return [
            'title' => 'Validar requisito', 'objective' => 'Confirmar o comportamento.',
            'preconditions' => 'Projeto preparado.', 'test_data' => 'Massa controlada.',
            'steps' => 'Executar o fluxo.', 'expected_result' => 'Fluxo concluído.',
            'severity' => 'high', 'status' => 'ready', 'assigned_tester_id' => null,
            'requirement_id' => $requirementId, 'change_request_id' => null, 'baseline_id' => null,
        ];
    }

    private function executionData(): array
    {
        return ['result' => 'passed', 'environment' => 'Homologação', 'observed_result' => 'Resultado esperado.', 'notes' => null, 'defect_reference' => null];
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

    private function scenario(OrganizationRole $organizationRole = OrganizationRole::Administrator): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $organization->id, 'user_id' => $user->id,
            'role_code' => $organizationRole, 'status' => OrganizationMembershipStatus::Active,
        ]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
        $project = Project::factory()->create(['manager_id' => $user->id]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => ProjectRole::ProjectManager, 'is_active' => true, 'started_at' => today()]);
        return [$organization, $user, $project, $membership];
    }
}
