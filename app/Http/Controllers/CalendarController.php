<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::query()
            ->visibleTo($request->user())
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
        $selectedProject = $projects->firstWhere('id', (int) $request->integer('project'));

        return $this->render($request, $projects, $selectedProject);
    }

    public function project(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        return $this->render($request, collect([$project]), $project);
    }

    private function render(Request $request, Collection $projects, ?Project $selectedProject): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'project' => ['nullable', 'integer'],
            'responsible' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', 'in:project_start,project_due,task_start,task_due,task_completed'],
        ]);
        $month = isset($filters['month'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters['month'].'-01')->startOfMonth()
            : CarbonImmutable::today()->startOfMonth();
        $gridStart = $month->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $visibleIds = $selectedProject ? [$selectedProject->id] : $projects->pluck('id')->all();
        $events = $this->events($visibleIds, $gridStart, $gridEnd, $filters);
        $days = collect();

        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $days->push([
                'date' => $day,
                'events' => $events->get($day->toDateString(), collect()),
            ]);
        }

        $unplannedTasks = Task::query()
            ->with('project')
            ->whereIn('project_id', $visibleIds)
            ->where('is_active', true)
            ->whereNull('start_date')
            ->whereNull('due_date')
            ->where('status', '!=', TaskStatus::Completed->value)
            ->latest('updated_at')
            ->limit(10)
            ->get();
        $responsibles = User::query()
            ->whereHas('assignedTasks', fn (Builder $query) => $query->whereIn('project_id', $visibleIds))
            ->orderBy('name')
            ->get();

        return view('calendar.index', compact(
            'projects',
            'selectedProject',
            'month',
            'days',
            'unplannedTasks',
            'responsibles',
        ));
    }

    private function events(
        array $projectIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $filters,
    ): Collection
    {
        $events = collect();
        $projects = Project::query()
            ->whereIn('id', $projectIds)
            ->where(function (Builder $query) use ($start, $end): void {
                $query->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('expected_end_date', [$start, $end]);
            })
            ->get();

        foreach ($projects as $project) {
            if ($project->start_date?->between($start, $end) && $this->acceptsType($filters, 'project_start')) {
                $this->pushEvent($events, $project->start_date->toDateString(), [
                    'type' => 'project_start',
                    'label' => 'Início · '.$project->code,
                    'title' => $project->name,
                    'url' => route('projects.show', $project),
                    'classes' => 'bg-[#E6F0F8] text-[#1D5D73]',
                ]);
            }

            if ($project->expected_end_date?->between($start, $end) && $this->acceptsType($filters, 'project_due')) {
                $this->pushEvent($events, $project->expected_end_date->toDateString(), [
                    'type' => 'project_due',
                    'label' => 'Entrega · '.$project->code,
                    'title' => $project->name,
                    'url' => route('projects.show', $project),
                    'classes' => $project->expected_end_date->lt(today())
                        ? 'bg-[#FBE8E8] text-[#A63A3A]'
                        : 'bg-[#FFF4DE] text-[#9A6415]',
                ]);
            }
        }

        $tasks = Task::query()
            ->with('project')
            ->whereIn('project_id', $projectIds)
            ->where('is_active', true)
            ->when($filters['responsible'] ?? null, fn (Builder $query, int $responsible) => $query
                ->where('responsible_id', $responsible))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query
                ->where('status', $status))
            ->where(function (Builder $query) use ($start, $end): void {
                $query->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('due_date', [$start, $end])
                    ->orWhereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()]);
            })
            ->get();

        foreach ($tasks as $task) {
            if ($task->start_date?->between($start, $end) && $this->acceptsType($filters, 'task_start')) {
                $this->pushEvent($events, $task->start_date->toDateString(), [
                    'type' => 'task_start',
                    'label' => 'Início · '.$task->code,
                    'title' => $task->title,
                    'url' => route('projects.tasks.show', [$task->project, $task]),
                    'classes' => 'bg-[#F2EAFB] text-[#6B4597]',
                ]);
            }

            if ($task->due_date?->between($start, $end) && $this->acceptsType($filters, 'task_due')) {
                $overdue = $task->due_date->lt(today()) && $task->status !== TaskStatus::Completed;
                $this->pushEvent($events, $task->due_date->toDateString(), [
                    'type' => 'task_due',
                    'label' => 'Prazo · '.$task->code,
                    'title' => $task->title,
                    'url' => route('projects.tasks.show', [$task->project, $task]),
                    'classes' => $overdue ? 'bg-[#FBE8E8] text-[#A63A3A]' : 'bg-[#E4F3F0] text-[#26735F]',
                ]);
            }

            if ($task->completed_at?->between($start->startOfDay(), $end->endOfDay())
                && $this->acceptsType($filters, 'task_completed')) {
                $this->pushEvent($events, $task->completed_at->toDateString(), [
                    'type' => 'task_completed',
                    'label' => 'Concluída · '.$task->code,
                    'title' => $task->title,
                    'url' => route('projects.tasks.show', [$task->project, $task]),
                    'classes' => 'bg-[#DDF3EB] text-[#26735F]',
                ]);
            }
        }

        return $events;
    }

    private function acceptsType(array $filters, string $type): bool
    {
        return empty($filters['type']) || $filters['type'] === $type;
    }

    private function pushEvent(Collection $events, string $date, array $event): void
    {
        $events->put($date, $events->get($date, collect())->push($event));
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless(
            $request->user()->isAdministrator() || $project->hasActiveMember($request->user()),
            403,
        );
    }
}
