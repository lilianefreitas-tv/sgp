<?php

namespace App\Http\Controllers;

use App\Enums\ContractEntryMode;
use App\Enums\ContractStatus;
use App\Models\Initiative;
use App\Models\ProjectContract;
use App\Services\OrganizationContext;
use App\Services\ProjectContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectContractController extends Controller
{
    public function index(): View
    {
        return view('contracts.index', ['contracts' => ProjectContract::with(['initiative', 'project'])->latest()->paginate(12)]);
    }

    public function create(Request $request): View
    {
        $initiative = $request->integer('initiative') ? Initiative::findOrFail($request->integer('initiative')) : null;

        return view('contracts.create', $this->options() + compact('initiative'));
    }

    public function store(Request $request, ProjectContractService $service): RedirectResponse
    {
        $contract = $service->create($this->validated($request), $request->user());

        return to_route('contracts.show', $contract)->with('success', 'Contrato registrado e versionado.');
    }

    public function show(ProjectContract $contract): View
    {
        return view('contracts.show', compact('contract') + ['contract' => $contract->load(['initiative', 'project', 'versions', 'attachments'])]);
    }

    public function edit(ProjectContract $contract): View
    {
        return view('contracts.edit', compact('contract') + $this->options());
    }

    public function update(Request $request, ProjectContract $contract, ProjectContractService $service): RedirectResponse
    {
        $service->update($contract, $this->validated($request), $request->user());

        return to_route('contracts.show', $contract)->with('success', 'Nova versão contratual registrada.');
    }

    public function download(ProjectContract $contract, int $attachment)
    {
        $file = $contract->attachments()->findOrFail($attachment);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    private function options(): array
    {
        return ['entryModes' => collect(ContractEntryMode::cases())->mapWithKeys(fn ($v) => [$v->value => $v->label()]), 'statuses' => collect(ContractStatus::cases())->mapWithKeys(fn ($v) => [$v->value => $v->label()])];
    }

    private function validated(Request $request): array
    {
        return $request->validate(['initiative_id' => ['nullable', Rule::exists('initiatives', 'id')->where(fn ($q) => $q->where('organization_id', app(OrganizationContext::class)->id()))],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where(fn ($q) => $q->where('organization_id', app(OrganizationContext::class)->id()))],
            'title' => 'required|string|max:200', 'contract_kind' => 'required|in:public_procurement,private,internal,other', 'entry_mode' => ['required', Rule::enum(ContractEntryMode::class)], 'status' => ['required', Rule::enum(ContractStatus::class)],
            'contracting_party' => 'nullable|string|max:200', 'contracted_party' => 'nullable|string|max:200', 'object' => 'nullable|string', 'content' => 'nullable|string|max:200000', 'external_reference' => 'nullable|string|max:150',
            'signed_at' => 'nullable|date', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date', 'amount' => 'nullable|numeric|min:0', 'capacity_notes' => 'nullable|string', 'reason' => 'required|string|max:300',
            'attachments' => 'nullable|array|max:10', 'attachments.*' => 'file|max:20480|mimes:pdf,doc,docx,xls,xlsx,txt,png,jpg,jpeg,zip']);
    }
}
