<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveChangeRequestImpactAnalysisRequest;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Services\ChangeRequestImpactAnalysisService;
use Illuminate\Http\RedirectResponse;

class ChangeRequestImpactAnalysisController extends Controller
{
    public function update(
        SaveChangeRequestImpactAnalysisRequest $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestImpactAnalysisService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        $service->saveDraft($changeRequest, $request->validated(), $request->user());

        return back()->with('success', 'Rascunho da análise de impacto salvo.');
    }

    public function complete(
        SaveChangeRequestImpactAnalysisRequest $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestImpactAnalysisService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        $service->complete($changeRequest, $request->validated(), $request->user());

        return back()->with('success', 'Análise de impacto concluída e congelada para decisão.');
    }

    private function ensureNested(Project $project, ChangeRequest $changeRequest): void
    {
        abort_unless($changeRequest->project_id === $project->id, 404);
    }
}
