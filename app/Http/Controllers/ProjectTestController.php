<?php

namespace App\Http\Controllers;

use App\Enums\HomologationStatus;
use App\Enums\ProjectRole;
use App\Enums\TestCaseSeverity;
use App\Enums\TestCaseStatus;
use App\Enums\TestExecutionResult;
use App\Http\Requests\StoreProjectTestCaseRequest;
use App\Models\Project;
use App\Models\ProjectTestCase;
use App\Models\User;
use App\Services\ProjectTestingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectTestController extends Controller
{
    public function overview(Request $request): View
    {
        $projects = Project::query()->visibleTo($request->user())
            ->withCount(['testCases as test_cases_count', 'homologations as homologations_count'])
            ->with(['testCases' => fn ($query) => $query->with('executions')->orderBy('sequence')])
            ->orderBy('name')->get();

        return view('tests.overview', compact('projects'));
    }

    public function index(Request $request, Project $project): View
    {
        $this->viewable($request, $project);
        $status = (string) $request->query('status');
        $severity = (string) $request->query('severity');
        $cases = $project->testCases()->with(['assignedTester', 'requirement', 'changeRequest', 'baseline', 'executions.evidences'])
            ->when(array_key_exists($status, TestCaseStatus::options()), fn ($query) => $query->where('status', $status))
            ->when(array_key_exists($severity, TestCaseSeverity::options()), fn ($query) => $query->where('severity', $severity))
            ->paginate(15)->withQueryString();
        $homologations = $project->homologations()->with(['decider', 'baseline'])->limit(10)->get();

        return view('tests.index', compact('project', 'cases', 'homologations', 'status', 'severity') + $this->options($project, $request));
    }

    public function create(Request $request, Project $project): View
    {
        abort_unless($request->user()->canPlanTests($project), 403);
        return view('tests.create', compact('project') + $this->options($project, $request));
    }

    public function store(StoreProjectTestCaseRequest $request, Project $project, ProjectTestingService $service): RedirectResponse
    {
        $case = $service->createCase($project, $request->validated(), $request->user());
        return to_route('projects.tests.show', [$project, $case])->with('success', 'Caso de teste registrado.');
    }

    public function show(Request $request, Project $project, ProjectTestCase $testCase): View
    {
        $this->nested($project, $testCase);
        $this->viewable($request, $project);
        $testCase->load(['assignedTester', 'requirement', 'changeRequest', 'baseline', 'creator', 'executions.executor', 'executions.evidences.uploader']);
        return view('tests.show', compact('project', 'testCase') + $this->options($project, $request));
    }

    public function edit(Request $request, Project $project, ProjectTestCase $testCase): View
    {
        $this->nested($project, $testCase);
        abort_unless($request->user()->canPlanTests($project), 403);
        return view('tests.edit', compact('project', 'testCase') + $this->options($project, $request));
    }

    public function update(StoreProjectTestCaseRequest $request, Project $project, ProjectTestCase $testCase, ProjectTestingService $service): RedirectResponse
    {
        $this->nested($project, $testCase);
        $service->updateCase($testCase, $request->validated(), $request->user());
        return to_route('projects.tests.show', [$project, $testCase])->with('success', 'Caso de teste atualizado.');
    }

    private function options(Project $project, Request $request): array
    {
        $testerIds = $project->memberships()->where('role', ProjectRole::Tester->value)->where('is_active', true)->pluck('user_id');
        return [
            'statuses' => TestCaseStatus::options(), 'severities' => TestCaseSeverity::options(),
            'results' => TestExecutionResult::options(), 'homologationStatuses' => HomologationStatus::options(),
            'testers' => User::query()->whereIn('id', $testerIds)->orderBy('name')->get(),
            'requirements' => $project->requirements()->where('is_active', true)->orderBy('code')->get(),
            'changeRequests' => $project->changeRequests()->orderBy('code')->get(),
            'baselines' => $project->baselines()->get(),
            'canPlan' => $request->user()->canPlanTests($project),
            'canExecute' => $request->user()->canExecuteTests($project),
            'canHomologate' => $request->user()->canHomologateProject($project),
        ];
    }

    private function viewable(Request $request, Project $project): void { abort_unless($request->user()->canAccessProject($project), 403); }
    private function nested(Project $project, ProjectTestCase $case): void { abort_unless($case->project_id === $project->id, 404); }
}
