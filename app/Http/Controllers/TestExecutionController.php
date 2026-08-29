<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTestExecutionRequest;
use App\Models\Project;
use App\Models\ProjectTestCase;
use App\Services\ProjectTestingService;
use Illuminate\Http\RedirectResponse;

class TestExecutionController extends Controller
{
    public function store(StoreTestExecutionRequest $request, Project $project, ProjectTestCase $testCase, ProjectTestingService $service): RedirectResponse
    {
        abort_unless($testCase->project_id === $project->id, 404);
        $service->execute($testCase, $request->validated(), $request->user());
        return back()->with('success', 'Execução registrada. O resultado anterior, quando existente, foi preservado.');
    }
}
