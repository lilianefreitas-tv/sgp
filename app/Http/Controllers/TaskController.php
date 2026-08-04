<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function overview(Request $request): View
    {
        $filters = $this->filters($request);
        $visibleProjects = Project::query()->visibleTo($request->user())->select('id');

        $tasks = Task::query()
            ->with(['project', 'requirement', 'responsible'])
            ->whereIn('project_id', $visibleProjects)
            ->search($filters['search'])
            ->when(array_key_exists($filters['status'], TaskStatus::options()),
                fn ($query) => $query->where('status', $filters['status']))
            ->when(array_key_exists($filters['priority'], TaskPriority::options()),
                fn ($query) => $query->where('priority', $filters['priority']))
            ->when($filters['responsibility'] === 'mine',
                fn ($query) => $query->where('responsible_id', $request->user()->id))
            ->when($filters['activity'] === 'inactive',
                fn ($query) => $query->where('is_active', false),
                fn ($query) => $query->where('is_active', true))
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('tasks.overview', compact('tasks') + $filters + $this->options());
    }

    public function index(Request $request, Project $project): View
    {
        $this->ensureCanView($request, $project);
        $filters = $this->filters($request);

        $tasks = $project->tasks()
            ->with(['requirement', 'responsible', 'parent'])
            ->search($filters['search'])
            ->when(array_key_exists($filters['status'], TaskStatus::options()),
                fn ($query) => $query->where('status', $filters['status']))
            ->when(array_key_exists($filters['priority'], TaskPriority::options()),
                fn ($query) => $query->where('priority', $filters['priority']))
            ->when($filters['responsibility'] === 'mine',
                fn ($query) => $query->where('responsible_id', $request->user()->id))
            ->when($filters['activity'] === 'inactive',
                fn ($query) => $query->where('is_active', false),
                fn ($query) => $query->where('is_active', true))
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('tasks.index', compact('project', 'tasks') + $filters + $this->options() + [
            'canManage' => $this->canManage($request, $project),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        abort_unless($this->canManage($request, $project), 403);

        return view('tasks.create', compact('project') + $this->formOptions($project));
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $task = DB::transaction(function () use ($request, $project): Task {
            $project->newQuery()->whereKey($project->id)->lockForUpdate()->first();
            $task = $project->tasks()->create($this->taskData($request));

            $task->histories()->create([
                'changed_by' => $request->user()->id,
                'event' => 'created',
                'to_status' => $task->status->value,
                'notes' => 'Tarefa cadastrada.',
                'created_at' => now(),
            ]);

            return $task;
        });

        return to_route('projects.tasks.show', [$project, $task])
            ->with('success', 'Tarefa cadastrada com sucesso.');
    }

    public function show(Request $request, Project $project, Task $task): View
    {
        $this->ensureBelongsToProject($project, $task);
        $this->ensureCanView($request, $project);
        $task->load(['responsible', 'requirement', 'parent', 'subtasks.responsible', 'histories.changedBy']);

        return view('tasks.show', compact('project', 'task') + [
            'canManage' => $this->canManage($request, $project),
        ]);
    }

    public function edit(Request $request, Project $project, Task $task): View
    {
        $this->ensureBelongsToProject($project, $task);
        abort_unless($this->canManage($request, $project), 403);

        return view('tasks.edit', compact('project', 'task') + $this->formOptions($project, $task));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $task);

        DB::transaction(function () use ($request, $task): void {
            $validated = $this->taskData($request);
            $notes = $validated['change_notes'] ?? null;
            unset($validated['change_notes']);

            $oldStatus = $task->status->value;
            $task->fill($validated);
            $changes = array_keys($task->getDirty());

            if ($changes !== []) {
                $task->save();
                $task->histories()->create([
                    'changed_by' => $request->user()->id,
                    'event' => in_array('status', $changes, true) ? 'status_changed' : 'updated',
                    'from_status' => $oldStatus,
                    'to_status' => $task->status->value,
                    'changed_fields' => $changes,
                    'notes' => $notes,
                    'created_at' => now(),
                ]);
            }
        });

        return to_route('projects.tasks.show', [$project, $task])
            ->with('success', 'Tarefa atualizada e alteração registrada no histórico.');
    }

    public function deactivate(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $task);
        abort_unless($this->canManage($request, $project), 403);
        $this->changeActivity($request, $task, false, 'Tarefa inativada.');

        return to_route('projects.tasks.index', $project)
            ->with('success', 'Tarefa inativada. O histórico foi preservado.');
    }

    public function reactivate(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $task);
        abort_unless($this->canManage($request, $project), 403);
        $this->changeActivity($request, $task, true, 'Tarefa reativada.');

        return to_route('projects.tasks.show', [$project, $task])
            ->with('success', 'Tarefa reativada com sucesso.');
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search')),
            'status' => (string) $request->query('status'),
            'priority' => (string) $request->query('priority'),
            'responsibility' => (string) $request->query('responsibility'),
            'activity' => (string) $request->query('activity', 'active'),
        ];
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'statuses' => TaskStatus::options(),
            'priorities' => TaskPriority::options(),
        ];
    }

    /** @return array<string, mixed> */
    private function formOptions(Project $project, ?Task $task = null): array
    {
        return $this->options() + [
            'members' => $project->memberships()
                ->where('is_active', true)->with('user')->get()
                ->pluck('user')->unique('id')->sortBy('name')->values(),
            'requirements' => $project->requirements()
                ->where('is_active', true)->orderBy('code')->get(),
            'parentTasks' => $project->tasks()
                ->where('is_active', true)->whereNull('parent_task_id')
                ->when($task, fn ($query) => $query->where('id', '!=', $task->id))
                ->orderBy('code')->get(),
        ];
    }

    private function ensureBelongsToProject(Project $project, Task $task): void
    {
        abort_unless($task->project_id === $project->id, 404);
    }

    private function ensureCanView(Request $request, Project $project): void
    {
        abort_unless(
            $request->user()->canAccessProject($project),
            403,
        );
    }

    private function canManage(Request $request, Project $project): bool
    {
        return $request->user()->administersCurrentOrganization()
            || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project)
            || $request->user()->hasProjectRole(ProjectRole::RequirementsAnalyst, $project)
            || $request->user()->hasProjectRole(ProjectRole::Developer, $project);
    }

    private function changeActivity(Request $request, Task $task, bool $active, string $notes): void
    {
        if ($task->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($request, $task, $active, $notes): void {
            $task->update(['is_active' => $active]);
            $task->histories()->create([
                'changed_by' => $request->user()->id,
                'event' => $active ? 'reactivated' : 'deactivated',
                'from_status' => $task->status->value,
                'to_status' => $task->status->value,
                'changed_fields' => ['is_active'],
                'notes' => $notes,
                'created_at' => now(),
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function taskData(StoreTaskRequest $request): array
    {
        $validated = $request->validated();
        $duration = $validated['estimated_duration'] ?? null;

        unset($validated['estimated_duration']);
        $validated['estimated_hours'] = Task::durationToDecimalHours($duration);

        return $validated;
    }
}
