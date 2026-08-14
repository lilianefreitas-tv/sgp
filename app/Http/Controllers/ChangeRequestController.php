<?php

namespace App\Http\Controllers;

use App\Enums\ChangeRequestOrigin;
use App\Enums\ChangeRequestState;
use App\Enums\ChangeRequestUrgency;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Http\Requests\StoreChangeRequestRequest;
use App\Http\Requests\UpdateChangeRequestRequest;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChangeRequestController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->ensureCanView($request, $project);
        $search = trim((string) $request->query('search'));
        $state = (string) $request->query('state');
        $urgency = (string) $request->query('urgency');

        $changeRequests = $project->changeRequests()
            ->with(['requester', 'analyst', 'baseline'])
            ->search($search)
            ->when(ChangeRequestState::tryFrom($state), fn ($query) => $query->where('state', $state))
            ->when(ChangeRequestUrgency::tryFrom($urgency), fn ($query) => $query->where('urgency', $urgency))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('projects.change-requests.index', compact(
            'project',
            'changeRequests',
            'search',
            'state',
            'urgency',
        ) + [
            'states' => ChangeRequestState::options(),
            'urgencies' => ChangeRequestUrgency::options(),
            'canCreate' => $request->user()->canContributeToProject($project),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        abort_unless($request->user()->canContributeToProject($project), 403);

        $changeRequest = new ChangeRequest([
            'requester_id' => $request->user()->id,
            'state' => ChangeRequestState::Draft,
        ]);

        return view('projects.change-requests.create', compact('project', 'changeRequest') + $this->formOptions($project, $request));
    }

    public function store(
        StoreChangeRequestRequest $request,
        Project $project,
        ChangeRequestService $service,
    ): RedirectResponse {
        $changeRequest = $service->create($project, $request->validated(), $request->user());

        return to_route('projects.change-requests.show', [$project, $changeRequest])
            ->with('success', "Solicitação {$changeRequest->code} registrada como rascunho.");
    }

    public function show(Request $request, Project $project, ChangeRequest $changeRequest): View
    {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('view', $changeRequest), 403);
        $changeRequest->load([
            'requester',
            'analyst',
            'baseline',
            'affectedItems',
            'transitions.actor',
            'attachments.uploader',
        ]);

        return view('projects.change-requests.show', compact('project', 'changeRequest') + $this->formOptions($project, $request));
    }

    public function edit(Request $request, Project $project, ChangeRequest $changeRequest): View
    {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('update', $changeRequest), 403);
        $changeRequest->load('affectedItems');

        return view('projects.change-requests.edit', compact('project', 'changeRequest') + $this->formOptions($project, $request));
    }

    public function update(
        UpdateChangeRequestRequest $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        $service->update($changeRequest, $request->validated(), $request->user());

        return to_route('projects.change-requests.show', [$project, $changeRequest])
            ->with('success', 'Solicitação atualizada.');
    }

    public function submit(
        Request $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('submit', $changeRequest), 403);
        $service->transition($changeRequest, ChangeRequestState::Submitted, $request->user());

        return back()->with('success', 'Solicitação submetida para análise.');
    }

    public function startAnalysis(
        Request $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('startAnalysis', $changeRequest), 403);
        $data = $request->validate(['analyst_id' => ['nullable', 'integer']]);
        $service->transition(
            $changeRequest,
            ChangeRequestState::UnderAnalysis,
            $request->user(),
            null,
            isset($data['analyst_id']) ? (int) $data['analyst_id'] : null,
        );

        return back()->with('success', 'Análise iniciada e responsável registrado.');
    }

    public function returnForAdjustment(
        Request $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('returnForAdjustment', $changeRequest), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->transition(
            $changeRequest,
            ChangeRequestState::Returned,
            $request->user(),
            $data['reason'],
        );

        return back()->with('success', 'Solicitação devolvida para ajustes.');
    }

    public function approve(
        Request $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('decide', $changeRequest), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->transition(
            $changeRequest,
            ChangeRequestState::Approved,
            $request->user(),
            $data['reason'],
        );

        return back()->with('success', 'Solicitação aprovada com parecer registrado.');
    }

    public function reject(
        Request $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('decide', $changeRequest), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->transition(
            $changeRequest,
            ChangeRequestState::Rejected,
            $request->user(),
            $data['reason'],
        );

        return back()->with('success', 'Solicitação rejeitada com motivo registrado.');
    }

    public function cancel(
        Request $request,
        Project $project,
        ChangeRequest $changeRequest,
        ChangeRequestService $service,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('cancel', $changeRequest), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->transition(
            $changeRequest,
            ChangeRequestState::Cancelled,
            $request->user(),
            $data['reason'],
        );

        return back()->with('success', 'Solicitação cancelada com histórico preservado.');
    }

    private function formOptions(Project $project, Request $request): array
    {
        $project->load([
            'baselines',
            'requirements' => fn ($query) => $query->where('is_active', true)->orderBy('code'),
            'tasks' => fn ($query) => $query->where('is_active', true)->orderBy('code'),
            'artifacts' => fn ($query) => $query->whereNull('archived_at')->orderBy('code'),
            'contracts' => fn ($query) => $query->orderBy('code'),
            'documents' => fn ($query) => $query->latest('generated_at'),
        ]);

        return [
            'origins' => ChangeRequestOrigin::options(),
            'urgencies' => ChangeRequestUrgency::options(),
            'projectUsers' => $this->projectUsers($project),
            'canManageProject' => $request->user()->canManageProject($project),
            'maxUploadMb' => config('sgp.attachments.max_kb') / 1024,
            'allowedExtensions' => config('sgp.attachments.allowed_extensions'),
        ];
    }

    private function projectUsers(Project $project)
    {
        $memberIds = $project->memberships()
            ->where('is_active', true)
            ->pluck('user_id')
            ->push($project->manager_id)
            ->unique();

        return User::query()
            ->where('is_active', true)
            ->whereHas('organizationMemberships', fn ($query) => $query
                ->where('organization_id', $project->organization_id)
                ->where('status', OrganizationMembershipStatus::Active->value))
            ->where(function ($query) use ($memberIds, $project): void {
                $query->whereIn('id', $memberIds)
                    ->orWhereHas('organizationMemberships', fn ($membership) => $membership
                        ->where('organization_id', $project->organization_id)
                        ->where('status', OrganizationMembershipStatus::Active->value)
                        ->whereIn('role_code', [
                            OrganizationRole::Owner->value,
                            OrganizationRole::Administrator->value,
                        ]));
            })
            ->orderBy('name')
            ->get();
    }

    private function ensureCanView(Request $request, Project $project): void
    {
        abort_unless($request->user()->canAccessProject($project), 403);
    }

    private function ensureNested(Project $project, ChangeRequest $changeRequest): void
    {
        abort_unless($changeRequest->project_id === $project->id, 404);
    }
}
