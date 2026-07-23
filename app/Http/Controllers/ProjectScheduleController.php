<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectScheduleController extends Controller
{
    public function __invoke(Request $request, Project $project): View
    {
        abort_unless(
            $request->user()->isAdministrator() || $project->hasActiveMember($request->user()),
            403,
        );

        $tasks = $project->tasks()
            ->with(['requirement', 'responsible'])
            ->where('is_active', true)
            ->orderBy('start_date')
            ->orderBy('due_date')
            ->orderBy('code')
            ->get();
        $plannedTasks = $tasks->filter(fn ($task) => $task->start_date || $task->due_date);
        $unplannedTasks = $tasks->filter(fn ($task) => ! $task->start_date && ! $task->due_date);

        [$timelineStart, $timelineEnd] = $this->timeline($project, $plannedTasks);
        $totalDays = max(1, $timelineStart->diffInDays($timelineEnd) + 1);
        $monthMarkers = collect();

        for ($cursor = $timelineStart->startOfMonth(); $cursor->lte($timelineEnd); $cursor = $cursor->addMonth()) {
            $visibleStart = $cursor->max($timelineStart);
            $visibleEnd = $cursor->endOfMonth()->min($timelineEnd);
            $monthMarkers->push([
                'label' => ucfirst($cursor->locale('pt_BR')->translatedFormat('M/Y')),
                'left' => ($timelineStart->diffInDays($visibleStart) / $totalDays) * 100,
                'width' => (($visibleStart->diffInDays($visibleEnd) + 1) / $totalDays) * 100,
            ]);
        }

        $groups = $plannedTasks
            ->groupBy(fn ($task) => $task->requirement_id ?: 'unlinked')
            ->map(function (Collection $items) use ($timelineStart, $totalDays) {
                return $items->map(function ($task) use ($timelineStart, $totalDays) {
                    $start = CarbonImmutable::parse($task->start_date ?? $task->due_date);
                    $end = CarbonImmutable::parse($task->due_date ?? $task->start_date);
                    $task->gantt_left = ($timelineStart->diffInDays($start) / $totalDays) * 100;
                    $task->gantt_width = max(1.2, (($start->diffInDays($end) + 1) / $totalDays) * 100);
                    $task->progress_percentage = $task->status === TaskStatus::Completed ? 100 : 0;

                    return $task;
                });
            });

        return view('schedules.show', compact(
            'project',
            'groups',
            'unplannedTasks',
            'timelineStart',
            'timelineEnd',
            'monthMarkers',
            'totalDays',
        ));
    }

    private function timeline(Project $project, Collection $tasks): array
    {
        $dates = $tasks
            ->flatMap(fn ($task) => [$task->start_date, $task->due_date])
            ->filter()
            ->map(fn ($date) => CarbonImmutable::parse($date));

        $start = $dates->min() ?? CarbonImmutable::parse($project->start_date ?? today());
        $end = $dates->max() ?? CarbonImmutable::parse($project->expected_end_date ?? $start->addDays(30));

        if ($start->diffInDays($end) < 13) {
            $end = $start->addDays(13);
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }
}
