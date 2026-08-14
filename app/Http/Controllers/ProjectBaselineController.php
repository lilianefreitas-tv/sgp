<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Services\ProjectBaselineService;
use App\Services\ProjectBaselineComparisonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectBaselineController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorizeView($request, $project);
        $project->load(['requirements' => fn ($q) => $q->where('is_active', true), 'artifacts' => fn ($q) => $q->whereNull('archived_at'),
            'contracts', 'originDocumentVersions' => fn ($q) => $q->where('origin_status', 'current'),
            'baselines' => fn ($q) => $q->with(['creator', 'items'])->latest('version')]);

        return view('projects.baselines.index', ['project' => $project, 'canManage' => $this->canManage($request, $project)]);
    }

    public function store(Request $request, Project $project, ProjectBaselineService $service): RedirectResponse
    {
        abort_unless($this->canManage($request, $project), 403);
        $data = $request->validate([
            'title' => 'required|string|max:160', 'justification' => 'required|string|max:1000',
            'requirements' => 'nullable|array', 'requirements.*' => [Rule::exists('requirements', 'id')->where('project_id', $project->id)],
            'artifacts' => 'nullable|array', 'artifacts.*' => [Rule::exists('artifacts', 'id')->where('project_id', $project->id)],
            'contracts' => 'nullable|array', 'contracts.*' => [Rule::exists('project_contracts', 'id')->where('project_id', $project->id)],
            'origin_documents' => 'nullable|array', 'origin_documents.*' => [Rule::exists('project_attachments', 'id')->where('project_id', $project->id)->where('is_origin_document', true)],
        ]);
        $baseline = $service->create($project, $data, $request->user());

        return to_route('projects.baselines.show', [$project, $baseline])->with('success', "Baseline {$baseline->version} constituída.");
    }

    public function show(Request $request, Project $project, ProjectBaseline $baseline): View
    {
        $this->authorizeView($request, $project);
        abort_unless($baseline->project_id === $project->id, 404);

        return view('projects.baselines.show', ['project' => $project, 'baseline' => $baseline->load(['items', 'creator'])]);
    }

    public function compare(Request $request, Project $project, ProjectBaseline $from, ProjectBaseline $to, ProjectBaselineComparisonService $service): View
    {
        $this->authorizeView($request, $project);
        abort_unless($from->project_id === $project->id && $to->project_id === $project->id && $from->id !== $to->id, 404);
        if ($from->version > $to->version) { [$from, $to] = [$to, $from]; }
        $from->load(['items', 'creator']); $to->load(['items', 'creator']);

        return view('projects.baselines.compare', compact('project', 'from', 'to') + $service->compare($from, $to));
    }

    private function authorizeView(Request $request, Project $project): void
    {
        abort_unless($request->user()->administersCurrentOrganization() || $project->hasActiveMember($request->user()), 403);
    }

    private function canManage(Request $request, Project $project): bool
    {
        return $request->user()->administersCurrentOrganization() || $project->manager_id === $request->user()->id;
    }
}
