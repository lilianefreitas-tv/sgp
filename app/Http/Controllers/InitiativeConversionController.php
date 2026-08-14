<?php

namespace App\Http\Controllers;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use App\Models\Client;
use App\Models\Initiative;
use App\Services\InitiativeConfigurationService;
use App\Services\InitiativeConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

class InitiativeConversionController extends Controller
{
    public function create(): View
    {
        return view('initiatives.create', [
            'origins' => InitiativeOrigin::cases(),
            'executionNatures' => ExecutionNature::cases(),
            'financialModes' => FinancialManagementMode::cases(),
            'managementLevels' => collect(ManagementLevel::cases())->reject(fn (ManagementLevel $level) => $level === ManagementLevel::Simplified),
            'methodologies' => ProjectMethodology::cases(),
        ]);
    }

    public function store(Request $request, InitiativeConfigurationService $initiatives): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'context' => ['nullable', 'string', 'max:10000'],
            'origin' => ['required', 'string'], 'execution_nature' => ['required', 'string'],
            'financial_management_mode' => ['required', 'string'], 'management_level' => ['required', 'string'],
            'methodology' => ['required', 'string'], 'justification' => ['required', 'string', 'max:10000'],
        ]);
        try {
            $initiatives->create(collect($data)->except('justification')->all(), $request->user(), $data['justification']);
        } catch (LogicException $exception) {
            return back()->withErrors(['initiative' => $exception->getMessage()])->withInput();
        }

        return to_route('initiatives.index')->with('success', 'Iniciativa criada com a configuração inicial.');
    }

    public function index(Request $request, InitiativeConversionService $conversions): View
    {
        $initiatives = Initiative::query()->with('project')->latest('updated_at')->get();
        try {
            $availability = $initiatives->mapWithKeys(fn (Initiative $initiative) => [
                $initiative->id => $conversions->availability($initiative, $request->user()),
            ]);
        } catch (LogicException) {
            abort(403);
        }

        return view('initiatives.index', [
            'initiatives' => $initiatives,
            'availability' => $availability,
        ]);
    }

    public function show(Request $request, Initiative $initiative, InitiativeConversionService $conversions): View
    {
        try {
            $availability = $conversions->availability($initiative, $request->user());
        } catch (LogicException) {
            abort(403);
        }

        return view('initiatives.conversion', [
            'initiative' => $initiative,
            'availability' => $availability,
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function convert(Request $request, Initiative $initiative, InitiativeConversionService $conversions): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'objective' => ['required', 'string', 'max:5000'],
            'justification' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $project = $conversions->convert($initiative, $data, $request->user());
        } catch (LogicException $exception) {
            abort(409, $exception->getMessage());
        }

        return to_route('projects.show', $project)->with('success', 'Iniciativa convertida em projeto.');
    }
}
