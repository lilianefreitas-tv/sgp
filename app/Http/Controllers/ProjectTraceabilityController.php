<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectTraceabilityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectTraceabilityController extends Controller
{
    public function overview(Request $request, ProjectTraceabilityService $service): View
    {
        $projects = Project::query()->visibleTo($request->user())->orderBy('name')->get()
            ->map(fn (Project $project): array => ['project' => $project, 'summary' => $service->summary($project)]);

        return view('traceability.overview', compact('projects'));
    }

    public function show(Request $request, Project $project, ProjectTraceabilityService $service): View
    {
        abort_unless($request->user()->canAccessProject($project), 403);
        $matrix = $service->matrix($project);

        return view('traceability.show', compact('project', 'matrix'));
    }
}
