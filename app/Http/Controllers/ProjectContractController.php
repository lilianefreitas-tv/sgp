<?php

namespace App\Http\Controllers;

use App\Enums\ContractEntryMode;
use App\Enums\ContractStatus;
use App\Models\Initiative;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Services\OrganizationContext;
use App\Services\OrganizationAuditService;
use App\Services\ProjectContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use LogicException;
use Throwable;

class ProjectContractController extends Controller
{
    public function index(Request $request): View
    {
        $project = $request->integer('project') ? Project::query()->findOrFail($request->integer('project')) : null;
        if ($project !== null) {
            abort_unless($request->user()->canAccessProject($project), 403);
        }

        return view('contracts.index', [
            'contracts' => ProjectContract::with(['initiative', 'project'])
                ->when($project, fn ($query) => $query->where('project_id', $project->id))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'project' => $project,
        ]);
    }

    public function create(Request $request): View
    {
        $initiative = $request->integer('initiative') ? Initiative::findOrFail($request->integer('initiative')) : null;
        $project = $request->integer('project') ? Project::query()->findOrFail($request->integer('project')) : $initiative?->project;
        if ($project !== null) {
            abort_unless($request->user()->canManageProject($project), 403);
            if ($initiative !== null && (int) $project->initiative_id !== (int) $initiative->id) {
                abort(409, 'O projeto não corresponde à iniciativa informada.');
            }
            $initiative = $project->initiative;
        }

        return view('contracts.create', $this->options($request) + compact('initiative', 'project'));
    }

    public function store(Request $request, ProjectContractService $service): RedirectResponse
    {
        $data = $this->validated($request);
        if (filled($data['project_id'] ?? null)) {
            $project = Project::query()->findOrFail((int) $data['project_id']);
            abort_unless($request->user()->canManageProject($project), 403);
        }
        try {
            $contract = $service->create($data, $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['contract_context' => $exception->getMessage()])->withInput();
        }

        return to_route('contracts.show', $contract)->with('success', 'Contrato registrado e versionado.');
    }

    public function show(Request $request, ProjectContract $contract): View
    {
        $contract->load(['initiative', 'project', 'versions', 'attachments']);
        abort_unless($this->canView($request, $contract), 403);
        foreach ($contract->attachments as $attachment) {
            try {
                $attachment->file_available = Storage::disk($attachment->disk)->exists($attachment->path);
            } catch (Throwable) {
                $attachment->file_available = false;
            }
        }

        $availableProjects = $contract->project_id === null
            ? Project::query()->visibleTo($request->user())->orderBy('name')->get()
                ->filter(fn (Project $project) => $request->user()->canManageProject($project))
                ->filter(fn (Project $project) => $contract->initiative_id === null
                    || (int) $project->initiative_id === (int) $contract->initiative_id)
            : collect();

        return view('contracts.show', compact('contract', 'availableProjects'));
    }

    public function edit(Request $request, ProjectContract $contract): View
    {
        abort_unless($this->canView($request, $contract), 403);

        return view('contracts.edit', compact('contract') + $this->options($request));
    }

    public function update(Request $request, ProjectContract $contract, ProjectContractService $service): RedirectResponse
    {
        $service->update($contract, $this->validated($request), $request->user());

        return to_route('contracts.show', $contract)->with('success', 'Nova versão contratual registrada.');
    }

    public function linkProject(Request $request, ProjectContract $contract, ProjectContractService $service): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')->where(fn ($query) => $query
                ->where('organization_id', app(OrganizationContext::class)->id()))],
        ]);
        $project = Project::query()->findOrFail((int) $data['project_id']);
        abort_unless($request->user()->canManageProject($project), 403);

        try {
            $service->linkToProject($contract, $project, $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['project_id' => $exception->getMessage()]);
        }

        return to_route('contracts.show', $contract)->with('success', 'Contrato vinculado ao projeto e nova versão registrada.');
    }

    public function download(
        Request $request,
        ProjectContract $contract,
        int $attachment,
        OrganizationAuditService $audit,
    ): StreamedResponse
    {
        $file = $contract->attachments()->findOrFail($attachment);

        if (! $this->canView($request, $contract)) {
            $audit->record('contract.attachment.download', 'denied', 'contract_attachment', $file->id, [
                'contract_id' => $contract->id,
                'project_id' => $contract->project_id,
                'reason' => 'contract_authorization',
            ]);
            abort(404);
        }

        try {
            $exists = Storage::disk($file->disk)->exists($file->path);
        } catch (Throwable) {
            $exists = false;
        }
        if (! $exists) {
            $audit->record('contract.attachment.download', 'failed', 'contract_attachment', $file->id, [
                'contract_id' => $contract->id,
                'project_id' => $contract->project_id,
                'reason' => 'file_missing',
            ]);
            abort(404, 'O arquivo contratual não está disponível no armazenamento privado. Registre uma nova versão e reenvie o documento.');
        }

        $audit->record('contract.attachment.download', 'success', 'contract_attachment', $file->id, [
            'contract_id' => $contract->id,
            'project_id' => $contract->project_id,
            'checksum' => $file->checksum,
        ]);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    private function canView(Request $request, ProjectContract $contract): bool
    {
        return $contract->project === null
            || $request->user()->canAccessProject($contract->project);
    }

    private function options(Request $request): array
    {
        return [
            'entryModes' => collect(ContractEntryMode::cases())->mapWithKeys(fn ($v) => [$v->value => $v->label()]),
            'statuses' => collect(ContractStatus::cases())->mapWithKeys(fn ($v) => [$v->value => $v->label()]),
            'initiatives' => Initiative::query()->orderBy('title')->get(),
            'projects' => Project::query()->visibleTo($request->user())->orderBy('name')->get()
                ->filter(fn (Project $project) => $request->user()->canManageProject($project)),
        ];
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
