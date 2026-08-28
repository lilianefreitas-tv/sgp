<?php

namespace Tests\Feature;

use App\Enums\ContractEntryMode;
use App\Enums\ContractStatus;
use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Enums\ProjectMethodology;
use App\Enums\ProjectStatus;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ProjectContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ProjectContractIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_editor_targets_the_contract_form_and_saved_content_is_visible(): void
    {
        [$organization, $user, $project] = $this->scenario();

        $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
            ->get(route('contracts.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('id="contract-form"', false)
            ->assertSee("editor?.closest('form')", false);

        $response = $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
            ->post(route('contracts.store'), $this->contractData([
                'project_id' => $project->id,
                'content' => '<p><strong>Conteúdo preservado</strong></p><script>alert(1)</script>',
            ]));

        $contract = ProjectContract::query()->firstOrFail();
        $response->assertRedirect(route('contracts.show', $contract));
        $this->assertSame('<p><strong>Conteúdo preservado</strong></p>', $contract->content);
        $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('Conteúdo preservado')
            ->assertDontSee('alert(1)');
    }

    public function test_standalone_contract_can_be_linked_to_existing_project_and_derives_initiative(): void
    {
        [, $user, $project] = $this->scenario(true);
        $contract = app(ProjectContractService::class)->create($this->contractData(), $user);

        $linked = app(ProjectContractService::class)->linkToProject($contract, $project, $user);

        $this->assertSame($project->id, $linked->project_id);
        $this->assertSame($project->initiative_id, $linked->initiative_id);
        $this->assertSame(2, $linked->versions->max('version'));
        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'event_type' => 'contract_linked',
            'subject_id' => $contract->id,
        ]);
    }

    public function test_standalone_contract_can_still_create_a_new_project(): void
    {
        [$organization, $user] = $this->scenario();
        $contract = app(ProjectContractService::class)->create($this->contractData(), $user);

        $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
            ->post(route('projects.store'), [
                'contract_id' => $contract->id,
                'manager_id' => $user->id,
                'origin_type' => 'incorporated',
                'name' => 'Projeto originado no contrato',
                'objective' => 'Executar o objeto contratado.',
                'execution_nature' => ExecutionNature::Contracted->value,
                'financial_management_mode' => FinancialManagementMode::FixedPrice->value,
                'management_level' => ManagementLevel::Essential->value,
                'methodology' => ProjectMethodology::Kanban->value,
                'status' => ProjectStatus::Planning->value,
                'is_active' => 1,
            ])->assertRedirect();

        $project = Project::query()->where('name', 'Projeto originado no contrato')->firstOrFail();
        $this->assertSame($project->id, $contract->fresh()->project_id);
        $this->assertSame(2, $contract->fresh()->versions()->max('version'));
    }

    public function test_contract_created_inside_project_inherits_project_and_initiative(): void
    {
        [$organization, $user, $project] = $this->scenario(true);

        $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
            ->post(route('contracts.store'), $this->contractData(['project_id' => $project->id]))
            ->assertRedirect();

        $contract = ProjectContract::query()->firstOrFail();
        $this->assertSame($project->id, $contract->project_id);
        $this->assertSame($project->initiative_id, $contract->initiative_id);
        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'event_type' => 'contract_created_for_project',
        ]);
    }

    public function test_mismatched_initiative_is_rejected(): void
    {
        [, $user, $project] = $this->scenario(true);
        $otherInitiative = Initiative::factory()->forOrganization($project->organization)->withActor($user)->create();

        $this->expectException(LogicException::class);
        app(ProjectContractService::class)->create($this->contractData([
            'project_id' => $project->id,
            'initiative_id' => $otherInitiative->id,
        ]), $user);
    }

    public function test_existing_project_link_cannot_be_reassigned_silently(): void
    {
        [, $user, $firstProject] = $this->scenario();
        $secondProject = Project::factory()->create(['manager_id' => $user->id]);
        $this->assignManager($secondProject, $user);
        $contract = app(ProjectContractService::class)->create($this->contractData(['project_id' => $firstProject->id]), $user);

        $this->expectException(LogicException::class);
        app(ProjectContractService::class)->linkToProject($contract, $secondProject, $user);
    }

    public function test_contract_from_another_organization_cannot_be_linked(): void
    {
        [$organizationA, $userA, $projectA] = $this->scenario();
        $contract = app(ProjectContractService::class)->create($this->contractData(['project_id' => $projectA->id]), $userA);

        app(OrganizationContext::class)->clear();
        [$organizationB, $userB, $projectB] = $this->scenario();

        $this->actingAs($userB)->withSession(['active_organization_id' => $organizationB->id])
            ->patch('/contracts/'.$contract->id.'/project', ['project_id' => $projectB->id])
            ->assertNotFound();
        $this->assertNotSame($organizationA->id, $organizationB->id);
    }

    /** @return array{0: Organization, 1: User, 2: Project} */
    private function scenario(bool $withInitiative = false): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_code' => OrganizationRole::Administrator,
            'status' => OrganizationMembershipStatus::Active,
        ]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));

        $initiative = $withInitiative
            ? Initiative::factory()->forOrganization($organization)->withActor($user)->create(['origin' => InitiativeOrigin::Commercial])
            : null;
        $project = Project::factory()->create([
            'manager_id' => $user->id,
            'initiative_id' => $initiative?->id,
        ]);
        $this->assignManager($project, $user);

        return [$organization, $user, $project];
    }

    private function assignManager(Project $project, User $user): void
    {
        $project->memberships()->create([
            'user_id' => $user->id,
            'role' => ProjectRole::ProjectManager,
            'is_active' => true,
            'started_at' => today(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function contractData(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Contrato integrado',
            'contract_kind' => 'private',
            'entry_mode' => ContractEntryMode::Editor->value,
            'status' => ContractStatus::Draft->value,
            'object' => 'Objeto contratual.',
            'content' => '<p>Conteúdo contratual.</p>',
            'reason' => 'Registro inicial.',
        ];
    }
}
