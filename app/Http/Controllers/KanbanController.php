<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\MoveKanbanTaskRequest;
use App\Http\Requests\UpdateKanbanColumnsRequest;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanTaskPosition;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KanbanController extends Controller
{
    public function overview(Request $request): View
    {
        $projects = Project::query()
            ->visibleTo($request->user())
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->with('client')
            ->withCount([
                'tasks as active_tasks_count' => fn (Builder $query) => $query->where('is_active', true),
                'tasks as completed_tasks_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('status', TaskStatus::Completed->value),
            ])
            ->orderBy('name')
            ->paginate(12);

        return view('kanban.overview', compact('projects'));
    }

    public function show(Request $request, Project $project): View
    {
        $this->ensureCanView($request, $project);
        $board = $this->boardFor($project);
        $filters = [
            'responsible' => (string) $request->query('responsible'),
            'priority' => (string) $request->query('priority'),
            'requirement' => (string) $request->query('requirement'),
            'situation' => (string) $request->query('situation'),
        ];

        $tasks = $project->tasks()
            ->with(['responsible', 'requirement', 'kanbanPosition'])
            ->where('is_active', true)
            ->when(ctype_digit($filters['responsible']),
                fn (Builder $query) => $query->where('responsible_id', (int) $filters['responsible']))
            ->when(array_key_exists($filters['priority'], TaskPriority::options()),
                fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when(ctype_digit($filters['requirement']),
                fn (Builder $query) => $query->where('requirement_id', (int) $filters['requirement']))
            ->when($filters['situation'] === 'overdue',
                fn (Builder $query) => $query
                    ->whereDate('due_date', '<', today())
                    ->where('status', '!=', TaskStatus::Completed->value))
            ->when($filters['situation'] === 'on_time',
                fn (Builder $query) => $query
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '>=', today()))
            ->when($filters['situation'] === 'without_due_date',
                fn (Builder $query) => $query->whereNull('due_date'))
            ->get()
            ->sortBy(fn (Task $task) => [
                $task->kanbanPosition?->position ?? PHP_INT_MAX,
                $task->due_date?->format('Y-m-d') ?? '9999-12-31',
                $task->id,
            ])
            ->groupBy(fn (Task $task) => $task->status->value);

        $members = $project->memberships()
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->pluck('user')
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('kanban.show', compact('project', 'board', 'tasks', 'filters', 'members') + [
            'requirements' => $project->requirements()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
            'priorities' => TaskPriority::options(),
            'statuses' => TaskStatus::options(),
            'canMove' => $this->canManageTasks($request, $project),
            'canConfigure' => $this->canConfigure($request, $project),
        ]);
    }

    public function move(
        MoveKanbanTaskRequest $request,
        Project $project,
        Task $task,
    ): JsonResponse|RedirectResponse {
        $this->ensureBelongsToProject($project, $task);
        abort_unless($task->is_active, 422, 'Tarefas inativas não podem ser movimentadas no Kanban.');

        $newStatus = TaskStatus::from($request->validated('status'));
        $oldStatus = $task->status;

        DB::transaction(function () use ($request, $project, $task, $newStatus, $oldStatus): void {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $board = $this->boardFor($project);
            $column = $board->columns()->where('status', $newStatus->value)->firstOrFail();
            $nextPosition = ((int) $column->taskPositions()->max('position')) + 1;

            $lockedTask->update(['status' => $newStatus]);

            KanbanTaskPosition::query()->updateOrCreate(
                ['task_id' => $lockedTask->id],
                [
                    'kanban_column_id' => $column->id,
                    'position' => $nextPosition,
                ],
            );

            if ($oldStatus !== $newStatus) {
                $lockedTask->histories()->create([
                    'changed_by' => $request->user()->id,
                    'event' => 'kanban_moved',
                    'from_status' => $oldStatus->value,
                    'to_status' => $newStatus->value,
                    'changed_fields' => ['status'],
                    'notes' => "Movida no Kanban de {$oldStatus->label()} para {$newStatus->label()}.",
                    'created_at' => now(),
                ]);
            }
        });

        $message = "Tarefa movida para {$newStatus->label()}.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $newStatus->value,
                'status_label' => $newStatus->label(),
            ]);
        }

        return back()->with('success', $message);
    }

    public function updateColumns(
        UpdateKanbanColumnsRequest $request,
        Project $project,
    ): RedirectResponse {
        $board = $this->boardFor($project);

        DB::transaction(function () use ($request, $board): void {
            foreach ($request->validated('columns') as $columnData) {
                $board->columns()
                    ->where('status', $columnData['status'])
                    ->update([
                        'name' => $columnData['name'],
                        'position' => $columnData['position'] + 100,
                    ]);
            }

            foreach ($request->validated('columns') as $columnData) {
                $board->columns()
                    ->where('status', $columnData['status'])
                    ->update(['position' => $columnData['position']]);
            }
        });

        return back()->with('success', 'Colunas do Kanban atualizadas com sucesso.');
    }

    private function boardFor(Project $project): KanbanBoard
    {
        return DB::transaction(function () use ($project): KanbanBoard {
            $board = KanbanBoard::query()->firstOrCreate(
                ['project_id' => $project->id],
                ['name' => "Kanban de {$project->name}", 'is_active' => true],
            );

            if ($board->columns()->count() !== count(TaskStatus::cases())) {
                foreach (TaskStatus::cases() as $index => $status) {
                    KanbanColumn::query()->firstOrCreate(
                        [
                            'kanban_board_id' => $board->id,
                            'status' => $status->value,
                        ],
                        [
                            'name' => $status->label(),
                            'position' => $index + 1,
                            'is_active' => true,
                        ],
                    );
                }
            }

            return $board->load('columns');
        });
    }

    private function ensureCanView(Request $request, Project $project): void
    {
        abort_unless(
            $request->user()->isAdministrator() || $project->hasActiveMember($request->user()),
            403,
        );
    }

    private function canManageTasks(Request $request, Project $project): bool
    {
        return $request->user()->isAdministrator()
            || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project)
            || $request->user()->hasProjectRole(ProjectRole::RequirementsAnalyst, $project)
            || $request->user()->hasProjectRole(ProjectRole::Developer, $project);
    }

    private function canConfigure(Request $request, Project $project): bool
    {
        return $request->user()->isAdministrator()
            || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project);
    }

    private function ensureBelongsToProject(Project $project, Task $task): void
    {
        abort_unless($task->project_id === $project->id, 404);
    }
}
