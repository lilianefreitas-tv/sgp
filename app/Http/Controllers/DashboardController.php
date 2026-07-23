<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\RequirementStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectDocument;
use App\Models\Requirement;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $projects = Project::query()
            ->visibleTo($request->user())
            ->whereNull('archived_at');
        $projectIds = (clone $projects)->select('id');
        $requirements = Requirement::query()
            ->where('is_active', true)
            ->whereIn('project_id', (clone $projectIds));
        $tasks = Task::query()
            ->where('is_active', true)
            ->whereIn('project_id', (clone $projectIds));
        $today = today();

        $projectStatusCounts = (clone $projects)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $taskStatusCounts = (clone $tasks)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $progressProjects = (clone $projects)
            ->with(['client', 'manager'])
            ->withCount([
                'tasks as active_tasks_count' => fn (Builder $query) => $query->where('is_active', true),
                'tasks as completed_tasks_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('status', TaskStatus::Completed->value),
                'tasks as overdue_tasks_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->whereDate('due_date', '<', $today)
                    ->where('status', '!=', TaskStatus::Completed->value),
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->each(function (Project $project): void {
                $project->progress_percentage = $project->active_tasks_count > 0
                    ? (int) round(($project->completed_tasks_count / $project->active_tasks_count) * 100)
                    : 0;
            });

        return view('dashboard', [
            'activeProjectsCount' => (clone $projects)->where('is_active', true)->count(),
            'delayedProjectsCount' => (clone $projects)
                ->whereDate('expected_end_date', '<', $today)
                ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
                ->count(),
            'requirementsCount' => (clone $requirements)->count(),
            'pendingRequirementsCount' => (clone $requirements)
                ->whereIn('status', [
                    RequirementStatus::Proposed->value,
                    RequirementStatus::UnderAnalysis->value,
                ])
                ->count(),
            'pendingTasksCount' => (clone $tasks)
                ->where('status', '!=', TaskStatus::Completed->value)
                ->count(),
            'completedTasksCount' => (clone $tasks)
                ->where('status', TaskStatus::Completed->value)
                ->count(),
            'overdueTasksCount' => (clone $tasks)
                ->whereDate('due_date', '<', $today)
                ->where('status', '!=', TaskStatus::Completed->value)
                ->count(),
            'documentsCount' => ProjectDocument::query()
                ->whereIn('project_id', (clone $projectIds))
                ->count(),
            'projectStatuses' => collect(ProjectStatus::cases())->map(fn (ProjectStatus $status) => [
                'label' => $status->label(),
                'value' => (int) ($projectStatusCounts[$status->value] ?? 0),
                'color' => match ($status) {
                    ProjectStatus::Planning => '#D89427',
                    ProjectStatus::InProgress => '#287EA1',
                    ProjectStatus::InValidation => '#7752A5',
                    ProjectStatus::Completed => '#2E8B74',
                    ProjectStatus::Cancelled => '#C44B4B',
                },
            ]),
            'taskStatuses' => collect(TaskStatus::cases())->map(fn (TaskStatus $status) => [
                'label' => $status->label(),
                'value' => (int) ($taskStatusCounts[$status->value] ?? 0),
                'color' => match ($status) {
                    TaskStatus::Backlog => '#8A9AA3',
                    TaskStatus::ToDo => '#287EA1',
                    TaskStatus::InProgress => '#4B67A1',
                    TaskStatus::InReview => '#D89427',
                    TaskStatus::InTesting => '#7752A5',
                    TaskStatus::Completed => '#2E8B74',
                },
            ]),
            'progressProjects' => $progressProjects,
            'upcomingDeadlines' => (clone $tasks)
                ->with(['project', 'responsible'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', $today)
                ->where('status', '!=', TaskStatus::Completed->value)
                ->orderBy('due_date')
                ->limit(6)
                ->get(),
            'attentionProjects' => $progressProjects
                ->filter(fn (Project $project) => $project->overdue_tasks_count > 0
                    || ($project->expected_end_date?->isPast()
                        && ! in_array($project->status, [ProjectStatus::Completed, ProjectStatus::Cancelled], true)))
                ->take(5),
            'recentActivities' => ProjectActivity::query()
                ->with(['project', 'user'])
                ->whereIn('project_id', (clone $projectIds))
                ->latest('created_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
