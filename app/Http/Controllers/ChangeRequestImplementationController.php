<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveChangeRequestImplementationRequest;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Services\ChangeRequestImplementationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChangeRequestImplementationController extends Controller
{
    public function update(
        SaveChangeRequestImplementationRequest $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestImplementationService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        $service->saveDraft($changeRequest, $request->validated(), $request->user());

        return back()->with('success', 'Planejamento da implementação salvo.');
    }

    public function start(
        SaveChangeRequestImplementationRequest $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestImplementationService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('startImplementation', $changeRequest), 403);
        $service->saveDraft($changeRequest, $request->validated(), $request->user());
        $service->start($changeRequest, $request->user());

        return back()->with('success', 'Implementação iniciada com plano e responsável registrados.');
    }

    public function complete(
        SaveChangeRequestImplementationRequest $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestImplementationService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('completeImplementation', $changeRequest), 403);
        $service->saveDraft($changeRequest, $request->validated(), $request->user());
        $service->complete($changeRequest, $request->user());

        return back()->with('success', 'Mudança implementada, rastreabilidade encerrada e baseline tratada.');
    }

    private function ensureNested(Project $project, ChangeRequest $changeRequest): void
    {
        abort_unless($changeRequest->project_id === $project->id, 404);
    }
}
