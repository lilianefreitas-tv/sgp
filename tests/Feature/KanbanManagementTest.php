<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\KanbanBoard;
use App\Models\KanbanTaskPosition;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_board_creates_six_default_columns_and_groups_tasks(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
            'title' => 'Implementar quadro visual',
        ]);

        $this->actingAs($administrator)
            ->get(route('projects.kanban.show', $project))
            ->assertOk()
            ->assertSee($task->title)
            ->assertSee('Em Andamento');

        $board = KanbanBoard::firstOrFail();
        $this->assertSame(6, $board->columns()->count());
        $this->assertSame(
            TaskStatus::options(),
            $board->columns()->pluck('name', 'status')->all(),
        );
    }

    public function test_moving_card_updates_status_position_history_and_completion_date(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Backlog,
        ]);

        $this->actingAs($administrator)
            ->get(route('projects.kanban.show', $project))
            ->assertOk();

        $this->actingAs($administrator)
            ->patchJson(route('projects.kanban.tasks.move', [$project, $task]), [
                'status' => TaskStatus::Completed->value,
            ])
            ->assertOk()
            ->assertJsonPath('status', TaskStatus::Completed->value);

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertNotNull(KanbanTaskPosition::where('task_id', $task->id)->first());
        $this->assertDatabaseHas('task_histories', [
            'task_id' => $task->id,
            'changed_by' => $administrator->id,
            'event' => 'kanban_moved',
            'from_status' => TaskStatus::Backlog->value,
            'to_status' => TaskStatus::Completed->value,
        ]);
    }

    public function test_observer_can_view_board_but_cannot_move_cards(): void
    {
        $project = Project::factory()->create();
        $observer = User::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $this->addMember($project, $observer, ProjectRole::Observer);

        $this->actingAs($observer)
            ->get(route('projects.kanban.show', $project))
            ->assertOk()
            ->assertSee($task->title);

        $this->actingAs($observer)
            ->patch(route('projects.kanban.tasks.move', [$project, $task]), [
                'status' => TaskStatus::ToDo->value,
            ])
            ->assertForbidden();
    }

    public function test_manager_analyst_and_developer_can_move_cards(): void
    {
        foreach ([ProjectRole::ProjectManager, ProjectRole::RequirementsAnalyst, ProjectRole::Developer] as $role) {
            $project = Project::factory()->create();
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'project_id' => $project->id,
                'status' => TaskStatus::Backlog,
            ]);
            $this->addMember($project, $user, $role);

            $this->actingAs($user)
                ->patch(route('projects.kanban.tasks.move', [$project, $task]), [
                    'status' => TaskStatus::ToDo->value,
                ])
                ->assertSessionHasNoErrors();

            $this->assertSame(TaskStatus::ToDo, $task->fresh()->status);
        }
    }

    public function test_inactive_tasks_do_not_appear_on_board(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $activeTask = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Tarefa ativa',
        ]);
        $inactiveTask = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Tarefa inativa',
            'is_active' => false,
        ]);

        $this->actingAs($administrator)
            ->get(route('projects.kanban.show', $project))
            ->assertOk()
            ->assertSee($activeTask->title)
            ->assertDontSee($inactiveTask->title);
    }

    public function test_kanban_overview_only_lists_visible_active_projects(): void
    {
        $member = User::factory()->create();
        $visibleProject = Project::factory()->create(['name' => 'Projeto visível']);
        $hiddenProject = Project::factory()->create(['name' => 'Projeto restrito']);
        $this->addMember($visibleProject, $member, ProjectRole::Observer);

        $this->actingAs($member)
            ->get(route('kanban.index'))
            ->assertOk()
            ->assertSee($visibleProject->name)
            ->assertDontSee($hiddenProject->name);
    }

    public function test_task_from_another_project_cannot_be_moved_on_board(): void
    {
        $administrator = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($administrator)
            ->patch(route('projects.kanban.tasks.move', [$project, $task]), [
                'status' => TaskStatus::ToDo->value,
            ])
            ->assertNotFound();
    }

    public function test_manager_can_configure_column_names_and_order_but_developer_cannot(): void
    {
        $project = Project::factory()->create();
        $manager = User::factory()->create();
        $developer = User::factory()->create();
        $this->addMember($project, $manager, ProjectRole::ProjectManager);
        $this->addMember($project, $developer, ProjectRole::Developer);

        $this->actingAs($manager)->get(route('projects.kanban.show', $project))->assertOk();
        $board = KanbanBoard::firstOrFail();
        $columns = $board->columns()->get()->map(fn ($column, $index) => [
            'status' => $column->status->value,
            'name' => $index === 0 ? 'Ideias futuras' : $column->name,
            'position' => $index === 0 ? 2 : ($index === 1 ? 1 : $index + 1),
        ])->all();

        $this->actingAs($manager)
            ->patch(route('projects.kanban.columns.update', $project), ['columns' => $columns])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kanban_columns', [
            'kanban_board_id' => $board->id,
            'status' => TaskStatus::Backlog->value,
            'name' => 'Ideias futuras',
            'position' => 2,
        ]);

        $this->actingAs($developer)
            ->patch(route('projects.kanban.columns.update', $project), ['columns' => $columns])
            ->assertForbidden();
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
