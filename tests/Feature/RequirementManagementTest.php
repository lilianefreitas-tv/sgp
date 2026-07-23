<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_requirement_with_automatic_code(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();

        $response = $this->actingAs($administrator)
            ->post(route('projects.requirements.store', $project), $this->requirementData());

        $requirement = Requirement::firstOrFail();

        $response->assertRedirect(route('projects.requirements.show', [$project, $requirement]));
        $this->assertSame('REQ-001', $requirement->code);
        $this->assertSame(1, $requirement->current_version);
    }

    public function test_requirements_receive_sequential_codes_inside_each_project(): void
    {
        $administrator = User::factory()->administrator()->create();
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();

        $this->actingAs($administrator)
            ->post(route('projects.requirements.store', $firstProject), $this->requirementData(['title' => 'Primeiro']));
        $this->actingAs($administrator)
            ->post(route('projects.requirements.store', $firstProject), $this->requirementData(['title' => 'Segundo']));
        $this->actingAs($administrator)
            ->post(route('projects.requirements.store', $secondProject), $this->requirementData(['title' => 'Outro projeto']));

        $this->assertSame(
            ['REQ-001', 'REQ-002'],
            $firstProject->requirements()->orderBy('id')->pluck('code')->all(),
        );
        $this->assertSame('REQ-001', $secondProject->requirements()->firstOrFail()->code);
    }

    public function test_requirements_analyst_can_manage_requirements_but_developer_cannot(): void
    {
        $analyst = User::factory()->create();
        $developer = User::factory()->create();
        $project = Project::factory()->create();
        $this->addMember($project, $analyst, ProjectRole::RequirementsAnalyst);
        $this->addMember($project, $developer, ProjectRole::Developer);

        $this->actingAs($analyst)
            ->post(route('projects.requirements.store', $project), $this->requirementData())
            ->assertSessionHasNoErrors();

        $this->actingAs($developer)
            ->get(route('projects.requirements.create', $project))
            ->assertForbidden();

        $this->actingAs($developer)
            ->post(route('projects.requirements.store', $project), $this->requirementData())
            ->assertForbidden();
    }

    public function test_active_project_member_can_view_but_outsider_cannot(): void
    {
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->create();
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);
        $this->addMember($project, $member, ProjectRole::Observer);

        $this->actingAs($member)
            ->get(route('projects.requirements.show', [$project, $requirement]))
            ->assertOk()
            ->assertSee($requirement->title);

        $this->actingAs($outsider)
            ->get(route('projects.requirements.show', [$project, $requirement]))
            ->assertForbidden();
    }

    public function test_requirements_menu_opens_overview_with_visible_requirements(): void
    {
        $member = User::factory()->create();
        $visibleProject = Project::factory()->create(['name' => 'Projeto visível']);
        $hiddenProject = Project::factory()->create(['name' => 'Projeto restrito']);
        $visibleRequirement = Requirement::factory()->create([
            'project_id' => $visibleProject->id,
            'title' => 'Requisito visível',
        ]);
        $hiddenRequirement = Requirement::factory()->create([
            'project_id' => $hiddenProject->id,
            'title' => 'Requisito restrito',
        ]);
        $this->addMember($visibleProject, $member, ProjectRole::Observer);

        $this->actingAs($member)
            ->get(route('requirements.index'))
            ->assertOk()
            ->assertSee($visibleRequirement->title)
            ->assertDontSee($hiddenRequirement->title);
    }

    public function test_administrator_can_view_requirements_from_all_projects_in_overview(): void
    {
        $administrator = User::factory()->administrator()->create();
        $firstRequirement = Requirement::factory()->create();
        $secondRequirement = Requirement::factory()->create();

        $this->actingAs($administrator)
            ->get(route('requirements.index'))
            ->assertOk()
            ->assertSee($firstRequirement->title)
            ->assertSee($secondRequirement->title);
    }

    public function test_responsible_must_be_an_active_project_member(): void
    {
        $administrator = User::factory()->administrator()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($administrator)
            ->post(route('projects.requirements.store', $project), $this->requirementData([
                'responsible_id' => $outsider->id,
            ]))
            ->assertSessionHasErrors('responsible_id');

        $this->assertDatabaseCount('requirements', 0);
    }

    public function test_editing_requirement_preserves_previous_version(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'title' => 'Título original',
            'current_version' => 1,
        ]);

        $this->actingAs($administrator)
            ->put(route('projects.requirements.update', [$project, $requirement]), $this->requirementData([
                'title' => 'Título revisado',
                'change_reason' => 'Validação com o cliente.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $requirement->fresh()->current_version);
        $this->assertDatabaseHas('requirement_versions', [
            'requirement_id' => $requirement->id,
            'version_number' => 1,
            'title' => 'Título original',
            'changed_by' => $administrator->id,
            'change_reason' => 'Validação com o cliente.',
        ]);
    }

    public function test_deactivation_preserves_requirement_and_history(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);

        $this->actingAs($administrator)
            ->patch(route('projects.requirements.deactivate', [$project, $requirement]))
            ->assertRedirect(route('projects.requirements.index', $project));

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'is_active' => false,
            'current_version' => 2,
        ]);
        $this->assertDatabaseHas('requirement_versions', [
            'requirement_id' => $requirement->id,
            'version_number' => 1,
        ]);
    }

    public function test_requirement_from_another_project_returns_not_found(): void
    {
        $administrator = User::factory()->administrator()->create();
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();
        $requirement = Requirement::factory()->create(['project_id' => $firstProject->id]);

        $this->actingAs($administrator)
            ->get(route('projects.requirements.show', [$secondProject, $requirement]))
            ->assertNotFound();
    }

    /** @param array<string, mixed> $overrides */
    private function requirementData(array $overrides = []): array
    {
        return array_merge([
            'responsible_id' => null,
            'title' => 'Permitir cadastrar requisitos',
            'description' => 'O sistema deverá permitir o cadastro de requisitos no projeto.',
            'type' => RequirementType::Functional->value,
            'priority' => RequirementPriority::High->value,
            'status' => RequirementStatus::Proposed->value,
            'acceptance_criteria' => 'O requisito recebe código automático e fica disponível para consulta.',
            'source' => 'Documento de Visão',
            'is_active' => '1',
        ], $overrides);
    }

    private function addMember(Project $project, User $user, ProjectRole $role): void
    {
        ProjectMembership::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
            'started_at' => today(),
        ]);
    }
}
