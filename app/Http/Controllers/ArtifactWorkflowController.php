<?php

namespace App\Http\Controllers;

use App\Enums\ArtifactWorkflowDecisionType;
use App\Enums\DocumentRole;
use App\Models\Artifact;
use App\Models\ArtifactWorkflowRound;
use App\Models\User;
use App\Services\ArtifactWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

class ArtifactWorkflowController extends Controller
{
    public function assign(Request $request, Artifact $artifact, ArtifactWorkflowService $service): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer'], 'role' => ['required', 'in:'.implode(',', array_keys(DocumentRole::options()))]]);
        try {
            $service->assign($artifact, User::query()->findOrFail($data['user_id']), DocumentRole::from($data['role']), $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return back()->with('success', 'Papel documental atribuído.');
    }

    public function submit(Request $request, Artifact $artifact, ArtifactWorkflowService $service): RedirectResponse
    {
        $data = $request->validate(['justification' => ['required', 'string', 'max:10000']]);
        try {
            $service->submit($artifact, $request->user(), $data['justification']);
        } catch (LogicException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return back()->with('success', 'Revisão submetida para análise.');
    }

    public function decide(Request $request, ArtifactWorkflowRound $round, ArtifactWorkflowService $service): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:changes_requested,forwarded_for_approval,approved,rejected'], 'justification' => ['required', 'string', 'max:10000']]);
        try {
            $service->decide($round, $request->user(), ArtifactWorkflowDecisionType::from($data['decision']), $data['justification']);
        } catch (LogicException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return back()->with('success', 'Decisão documental registrada.');
    }
}
