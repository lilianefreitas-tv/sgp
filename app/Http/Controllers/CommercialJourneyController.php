<?php

namespace App\Http\Controllers;

use App\Models\Initiative;
use App\Models\Opportunity;
use App\Services\CommercialJourneyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

class CommercialJourneyController extends Controller
{
    public function index(): View
    {
        return view('commercial.index', [
            'initiatives' => Initiative::query()->where('origin', 'commercial')->with('opportunity')->get(),
        ]);
    }

    public function show(Initiative $initiative): View
    {
        abort_unless($initiative->origin->value === 'commercial', 404);

        return view('commercial.show', [
            'initiative' => $initiative->load(['contracts.project', 'project']),
            'opportunity' => $initiative->opportunity()->with(['assessments', 'proposals.versions', 'negotiations'])->first(),
        ]);
    }

    public function storeOpportunity(Request $request, Initiative $initiative, CommercialJourneyService $service): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'summary' => 'nullable|string',
            'priority' => 'required|in:low,normal,high',
            'estimated_value' => 'nullable|numeric',
            'expected_decision_at' => 'nullable|date',
            'commercial_owner_id' => 'nullable|integer',
        ]);

        try {
            $service->createOpportunity($initiative, $data, $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['commercial' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Oportunidade criada.');
    }

    public function assessment(Request $request, Opportunity $opportunity, CommercialJourneyService $service): RedirectResponse
    {
        $service->assessment($opportunity, $request->validate([
            'state' => 'required|in:draft,preparing,performed,consolidated,validated,cancelled',
            'needs' => 'nullable|string',
            'constraints' => 'nullable|string',
            'assumptions' => 'nullable|string',
            'objectives' => 'nullable|string',
            'participants' => 'nullable|string',
            'observations' => 'nullable|string',
        ]), $request->user());

        return back();
    }

    public function proposal(Request $request, Opportunity $opportunity, CommercialJourneyService $service): RedirectResponse
    {
        $service->proposal($opportunity, $request->validate([
            'scope_summary' => 'nullable|string',
            'solution_summary' => 'nullable|string',
            'pricing_model' => 'nullable|string',
            'estimated_value' => 'nullable|numeric',
            'change_reason' => 'nullable|string',
        ]), $request->user());

        return back();
    }

    public function negotiation(Request $request, Opportunity $opportunity, CommercialJourneyService $service): RedirectResponse
    {
        $service->negotiation($opportunity, $request->validate([
            'interaction_type' => 'required|in:meeting,email,phone,counterproposal,internal_analysis,decision,acceptance',
            'occurred_at' => 'required|date',
            'summary' => 'nullable|string',
            'counterproposal' => 'nullable|string',
            'decision' => 'nullable|string',
            'next_step' => 'nullable|string',
            'proposal_id' => 'nullable|integer',
            'proposal_version_id' => 'nullable|integer',
        ]), $request->user());

        return back();
    }

    public function transition(Request $request, Opportunity $opportunity, CommercialJourneyService $service): RedirectResponse
    {
        $data = $request->validate([
            'state' => 'required|in:qualified,under_discovery,under_proposal,under_negotiation,won,lost,suspended',
            'justification' => 'nullable|string',
        ]);

        try {
            $service->transition($opportunity, $data['state'], $request->user(), $data['justification'] ?? null);
        } catch (LogicException $exception) {
            return back()->withErrors(['state' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Etapa comercial atualizada.');
    }
}
