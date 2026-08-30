<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectHomologationRequest;
use App\Models\Project;
use App\Services\ProjectTestingService;
use Illuminate\Http\RedirectResponse;

class ProjectHomologationController extends Controller
{
    public function store(StoreProjectHomologationRequest $request, Project $project, ProjectTestingService $service): RedirectResponse
    {
        $service->homologate($project, $request->validated(), $request->user());
        return back()->with('success', 'Decisão formal de homologação registrada e preservada.');
    }
}
