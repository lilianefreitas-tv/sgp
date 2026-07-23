<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_task_with_automatic_code_and_history(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();

        $response = $this->actingAs($administrator)
            ->post(route('projects.tasks.store', $project), $this->taskData());

        $task = Task::firstOrFail();
        $response->assertRedirect(route('projects.tasks.show', [$project, $task]));
        $this->assertSame('TAR-001', $task->code);
        $this->assertSame('8.00', $task->estimated_hours);
        $this->assertDatabaseHas('task_histories', [
            'task_id' => $task->id,
            'changed_by' => $administrator->id,
            'event' => 'created',
        ]);
    }

    public function test_tasks_receive_sequential_codes_inside_each_project(): void
    {
        $administrator = User::factory()->administrator()->create();
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();

        $this->actingAs($administrator)->post(route('projects.tasks.store', $firstProject), $this->taskData(['title' => 'Primeira']));
        $this->actingAs($administrator)->post(route('projects.tasks.store', $firstProject), $this->taskData(['title' => 'Segunda']));
        $this->actingAs($administrator)->post(route('projects.tasks.store', $secondProject), $this->taskData(['title' => 'Outro projeto']));

        $this->assertSame(['TAR-001', 'TAR-002'], $firstProject->tasks()->orderBy('id')->pluck('code')->all());
        $this->assertSame('TAR-001', $secondProject->tasks()->firstOrFail()->code);
    }

    public function test_manager_analyst_and_developer_can_manage_tasks_but_observer_cannot(): void
    {
        $project = Project::factory()->create();

        foreach ([ProjectRole::ProjectManager, ProjectRole::RequirementsAnalyst, ProjectRole::Developer] as $role) {
            $user = User::factory()->create();
            $this->addMember($project, $user, $role);
            $this->actingAs($user)
                ->post(route('projects.tasks.store', $project), $this->taskData(['title' => $role->label()]))
                ->assertSessionHasNoErrors();
        }

        $observer = User::factory()->create();
        $this->addMember($project, $observer, ProjectRole::Observer);
        $this->actingAs($observer)->get(route('projects.tasks.create', $project))->assertForbidden();
    }

    public function test_responsible_requirement_and_parent_must_belong_to_same_project(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $outsider = User::factory()->create();
        $otherRequirement = Requirement::factory()->create(['project_id' => $otherProject->id]);
        $otherTask = Task::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($administrator)
            ->post(route('projects.tasks.store', $project), $this->taskData([
                'responsible_id' => $outsider->id,
                'requirement_id' => $otherRequirement->id,
                'parent_task_id' => $otherTask->id,
            ]))
            ->assertSessionHasErrors(['responsible_id', 'requirement_id', 'parent_task_id']);
    }

    public function test_completed_status_sets_completion_date_and_reopening_clears_it(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($administrator)
            ->put(route('projects.tasks.update', [$project, $task]), $this->taskData([
                'status' => TaskStatus::Completed->value,
            ]))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($task->fresh()->completed_at);

        $this->actingAs($administrator)
            ->put(route('projects.tasks.update', [$project, $task]), $this->taskData([
                'status' => TaskStatus::InProgress->value,
            ]))
            ->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_editing_task_registers_status_change_history(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Backlog,
        ]);

        $this->actingAs($administrator)
            ->put(route('projects.tasks.update', [$project, $task]), $this->taskData([
                'status' => TaskStatus::InProgress->value,
                'change_notes' => 'Execução iniciada.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('task_histories', [
            'task_id' => $task->id,
            'event' => 'status_changed',
            'from_status' => TaskStatus::Backlog->value,
            'to_status' => TaskStatus::InProgress->value,
            'notes' => 'Execução iniciada.',
        ]);
    }

    public function test_tasks_overview_only_shows_tasks_from_visible_projects(): void
    {
        $member = User::factory()->create();
        $visibleProject = Project::factory()->create();
        $hiddenProject = Project::factory()->create();
        $visibleTask = Task::factory()->create(['project_id' => $visibleProject->id, 'title' => 'Tarefa visível']);
        $hiddenTask = Task::factory()->create(['project_id' => $hiddenProject->id, 'title' => 'Tarefa restrita']);
        $this->addMember($visibleProject, $member, ProjectRole::Observer);

        $this->actingAs($member)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee($visibleTask->title)
            ->assertDontSee($hiddenTask->title);
    }

    public function test_deactivation_preserves_task_and_history(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($administrator)
            ->patch(route('projects.tasks.deactivate', [$project, $task]))
            ->assertRedirect(route('projects.tasks.index', $project));

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_active' => false]);
        $this->assertDatabaseHas('task_histories', ['task_id' => $task->id, 'event' => 'deactivated']);
    }

    public function test_subtask_cannot_be_used_as_parent_of_another_task(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $parent = Task::factory()->create(['project_id' => $project->id]);
        $subtask = Task::factory()->create(['project_id' => $project->id, 'parent_task_id' => $parent->id]);

        $this->actingAs($administrator)
            ->post(route('projects.tasks.store', $project), $this->taskData(['parent_task_id' => $subtask->id]))
            ->assertSessionHasErrors('parent_task_id');
    }

    public function test_duration_is_received_and_displayed_in_hours_and_minutes(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();

        $this->actingAs($administrator)
            ->post(route('projects.tasks.store', $project), $this->taskData([
                'estimated_duration' => '01:30',
            ]))
            ->assertSessionHasNoErrors();

        $task = Task::firstOrFail();
        $this->assertSame('1.50', $task->estimated_hours);

        $this->actingAs($administrator)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('01:30');
    }

    public function test_duration_rejects_invalid_minutes_and_zero_value(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();

        $this->actingAs($administrator)
            ->post(route('projects.tasks.store', $project), $this->taskData([
                'estimated_duration' => '01:60',
            ]))
            ->assertSessionHasErrors('estimated_duration');

        $this->actingAs($administrator)
            ->post(route('projects.tasks.store', $project), $this->taskData([
                'estimated_duration' => '00:00',
            ]))
            ->assertSessionHasErrors('estimated_duration');
    }

    /** @param array<string, mixed> $overrides */
    private function taskData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Implementar cadastro de tarefas',
            'description' => 'Criar telas, validações e histórico.',
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::Backlog->value,
            'responsible_id' => null,
            'requirement_id' => null,
            'parent_task_id' => null,
            'estimated_duration' => '08:00',
            'start_date' => '2026-07-23',
            'due_date' => '2026-07-30',
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
