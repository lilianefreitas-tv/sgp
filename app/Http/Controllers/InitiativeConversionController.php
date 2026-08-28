<?php

namespace App\Http\Controllers;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\InitiativeState;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use App\Models\Client;
use App\Models\Initiative;
use App\Models\ProjectContract;
use App\Services\InitiativeConfigurationService;
use App\Services\InitiativeConversionService;
use App\Services\ProjectContractService;
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
        $filter = $request->string('status')->toString() ?: 'active';
        $query = Initiative::query()->with('project')->latest('updated_at');
        match ($filter) {
            'converted' => $query->where('state', InitiativeState::Converted->value),
            'cancelled' => $query->where('state', InitiativeState::Cancelled->value),
            'archived' => $query->where('state', InitiativeState::Archived->value),
            'all' => null,
            default => $query->whereNotIn('state', [
                InitiativeState::Converted->value,
                InitiativeState::Cancelled->value,
                InitiativeState::Archived->value,
            ]),
        };
        $initiatives = $query->get();
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
            'filter' => in_array($filter, ['active', 'converted', 'cancelled', 'archived', 'all'], true) ? $filter : 'active',
        ]);
    }

    public function details(Request $request, Initiative $initiative, InitiativeConversionService $conversions): View
    {
        try {
            $availability = $conversions->availability($initiative, $request->user());
        } catch (LogicException) {
            abort(403);
        }

        return view('initiatives.show', [
            'initiative' => $initiative->load([
                'creator', 'project', 'contracts.versions',
                'configurationVersions' => fn ($query) => $query->latest('sequence'),
            ]),
            'availability' => $availability,
            'canManageLifecycle' => (int) $initiative->created_by === (int) $request->user()->id || $request->user()->canCreateProjects(),
            'availableContracts' => ProjectContract::query()
                ->whereNull('initiative_id')->whereNull('project_id')->latest()->get(),
        ]);
    }

    public function edit(Request $request, Initiative $initiative, InitiativeConversionService $conversions): View
    {
        try {
            $conversions->availability($initiative, $request->user());
        } catch (LogicException) {
            abort(403);
        }
        if ((int) $initiative->created_by !== (int) $request->user()->id && ! $request->user()->canCreateProjects()) {
            abort(403);
        }
        if ($initiative->project()->exists() || in_array($initiative->state, [InitiativeState::Converted, InitiativeState::Cancelled, InitiativeState::Archived], true)) {
            abort(409, 'A iniciativa não permite edição no estado atual.');
        }

        return view('initiatives.edit', $this->formOptions() + ['initiative' => $initiative]);
    }

    public function update(Request $request, Initiative $initiative, InitiativeConfigurationService $initiatives): RedirectResponse
    {
        $data = $request->validate($this->updateRules());
        try {
            $initiatives->update(
                $initiative,
                collect($data)->except(['justification', 'lock_version'])->all(),
                $request->user(),
                $data['justification'],
                (int) $data['lock_version'],
            );
        } catch (LogicException $exception) {
            return back()->withErrors(['initiative' => $exception->getMessage()])->withInput();
        }

        return to_route('initiatives.show', $initiative)->with('success', 'Iniciativa atualizada com rastreabilidade.');
    }

    public function cancel(Request $request, Initiative $initiative, InitiativeConfigurationService $initiatives): RedirectResponse
    {
        return $this->stateChange($request, $initiative, fn ($reason, $lock) => $initiatives->cancel($initiative, $request->user(), $reason, $lock), 'Iniciativa cancelada.');
    }

    public function archive(Request $request, Initiative $initiative, InitiativeConfigurationService $initiatives): RedirectResponse
    {
        return $this->stateChange($request, $initiative, fn ($reason, $lock) => $initiatives->archive($initiative, $request->user(), $reason, $lock), 'Iniciativa arquivada sem exclusão do histórico.');
    }

    public function restore(Request $request, Initiative $initiative, InitiativeConfigurationService $initiatives): RedirectResponse
    {
        return $this->stateChange($request, $initiative, fn ($reason, $lock) => $initiatives->restore($initiative, $request->user(), $reason, $lock), 'Iniciativa restaurada como rascunho.');
    }

    public function linkContract(
        Request $request,
        Initiative $initiative,
        ProjectContractService $contracts,
    ): RedirectResponse {
        $data = $request->validate([
            'contract_id' => ['required', 'integer'],
            'justification' => ['required', 'string', 'max:10000'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);
        try {
            $contract = ProjectContract::query()->findOrFail((int) $data['contract_id']);
            $contracts->linkToInitiative(
                $contract,
                $initiative,
                $request->user(),
                $data['justification'],
                (int) $data['lock_version'],
            );
        } catch (LogicException $exception) {
            return back()->withErrors(['contract' => $exception->getMessage()])->withInput();
        }

        return to_route('initiatives.show', $initiative)->with('success', 'Contrato vinculado à iniciativa e versionado.');
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

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'origins' => InitiativeOrigin::cases(),
            'executionNatures' => ExecutionNature::cases(),
            'financialModes' => FinancialManagementMode::cases(),
            'managementLevels' => collect(ManagementLevel::cases())->reject(fn (ManagementLevel $level) => $level === ManagementLevel::Simplified),
            'methodologies' => ProjectMethodology::cases(),
        ];
    }

    /** @return array<string, array<int, string>> */
    private function updateRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'], 'context' => ['nullable', 'string', 'max:10000'],
            'origin' => ['required', 'string'], 'execution_nature' => ['required', 'string'],
            'financial_management_mode' => ['required', 'string'], 'management_level' => ['required', 'string'],
            'methodology' => ['required', 'string'], 'justification' => ['required', 'string', 'max:10000'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    private function stateChange(Request $request, Initiative $initiative, callable $operation, string $success): RedirectResponse
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'max:10000'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);
        try {
            $operation($data['justification'], (int) $data['lock_version']);
        } catch (LogicException $exception) {
            return back()->withErrors(['initiative' => $exception->getMessage()]);
        }

        return to_route('initiatives.show', $initiative)->with('success', $success);
    }
}
