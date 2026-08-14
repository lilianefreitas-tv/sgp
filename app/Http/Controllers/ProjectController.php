<?php

namespace App\Http\Controllers;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use App\Enums\ProjectOriginType;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMembership;
use App\Models\ProjectContract;
use App\Models\User;
use App\Services\ProjectConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $level = (string) $request->query('level');
        $archive = (string) $request->query('archive', 'current');

        $projects = Project::query()
            ->visibleTo($request->user())
            ->with(['client', 'manager'])
            ->addSelect(['members_count' => ProjectMembership::query()
                ->selectRaw('count(distinct user_id)')
                ->whereColumn('project_id', 'projects.id')
                ->where('is_active', true)])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('code', "%{$search}%", caseSensitive: false);
            }))
            ->when(array_key_exists($status, ProjectStatus::options()),
                fn ($query) => $query->where('status', $status))
            ->when(array_key_exists($level, ManagementLevel::options()),
                fn ($query) => $query->where('management_level', $level))
            ->when($archive === 'archived',
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at'))
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('projects.index', compact('projects', 'search', 'status', 'level', 'archive'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canCreateProjects(), 403);

        $sourceContract = $request->integer('contract')
            ? ProjectContract::query()->whereNull('project_id')->findOrFail($request->integer('contract'))
            : null;

        return view('projects.create', $this->formOptions() + compact('sourceContract'));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = DB::transaction(function () use ($request): Project {
            $data = $this->normalizeDates($request->validated());
            $contractId = $data['contract_id'] ?? null;
            unset($data['contract_id']);
            $project = Project::create($data);
            if ($contractId) {
                ProjectContract::query()->whereNull('project_id')->findOrFail($contractId)->update(['project_id' => $project->id, 'updated_by' => $request->user()->id]);
            }

            $this->activateManagerMembership($project);
            ProjectActivity::record(
                $project,
                $request->user(),
                'project_created',
                'Projeto cadastrado',
                'project',
                $project->id,
                ['configuration' => $this->configurationSnapshot($project)],
            );

            return $project;
        });

        return to_route('projects.show', $project)->with('success', 'Projeto cadastrado com sucesso.');
    }

    public function show(Request $request, Project $project): View
    {
        $this->ensureCanView($request, $project);

        $project->load(['client', 'manager', 'memberships' => fn ($query) => $query
            ->where('is_active', true)
            ->with('user')
            ->orderBy('user_id')]);
        $project->load('originBaseline');
        $project->loadCount([
            'requirements as active_requirements_count' => fn ($query) => $query->where('is_active', true),
            'tasks as active_tasks_count' => fn ($query) => $query->where('is_active', true),
            'documents as documents_count',
            'originDocumentVersions as origin_document_versions_count',
            'baselines as baselines_count',
            'changeRequests as change_requests_count',
        ]);

        $members = $project->memberships
            ->groupBy('user_id')
            ->map(fn ($memberships) => [
                'user' => $memberships->first()->user,
                'roles' => $memberships->pluck('role'),
            ])
            ->values();

        $canManage = $this->canManage($request, $project);

        return view('projects.show', compact('project', 'members', 'canManage') + [
            'users' => $canManage
                ? $this->organizationUsers()
                : collect(),
            'roles' => ProjectRole::options(),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        abort_unless($this->canManage($request, $project), 403);

        return view('projects.edit', compact('project') + $this->formOptions($project));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project): void {
            $configurationBefore = $this->configurationSnapshot($project);
            $data = $this->normalizeDates($request->validated());
            unset($data['configuration_justification']);
            $dimensions = collect(['execution_nature', 'financial_management_mode', 'management_level', 'methodology'])
                ->filter(fn (string $field) => $data[$field] !== $project->{$field}->value)->mapWithKeys(fn (string $field) => [$field => $data[$field]])->all();
            $project->update(array_diff_key($data, array_flip(array_keys($dimensions))));
            if ($dimensions !== []) app(ProjectConfigurationService::class)->change($project, $dimensions, $request->user(), $request->input('configuration_justification'));
            $configurationAfter = $this->configurationSnapshot($project->fresh());

            $this->activateManagerMembership($project);

            $changedFields = array_values(array_diff(
                array_keys($project->getChanges()),
                ['updated_at'],
            ));
            if ($changedFields !== []) {
                $configurationChanges = collect($configurationAfter)
                    ->filter(fn (string $value, string $field): bool => $configurationBefore[$field] !== $value)
                    ->map(fn (string $value, string $field): array => [
                        'from' => $configurationBefore[$field],
                        'to' => $value,
                    ])
                    ->all();

                ProjectActivity::record(
                    $project,
                    $request->user(),
                    $configurationChanges === [] ? 'project_updated' : 'project_configuration_updated',
                    $configurationChanges === []
                        ? 'Informações do projeto atualizadas'
                        : 'Configuração adaptativa atualizada',
                    'project',
                    $project->id,
                    [
                        'fields' => $changedFields,
                        'configuration_changes' => $configurationChanges,
                        'details' => $configurationChanges === []
                            ? null
                            : collect($configurationChanges)
                                ->map(fn (array $change, string $field): string => sprintf(
                                    '%s: %s para %s',
                                    $this->configurationFieldLabel($field),
                                    $change['from'],
                                    $change['to'],
                                ))
                                ->implode('; '),
                    ],
                );
            }
        });

        return to_route('projects.show', $project)->with('success', 'Projeto atualizado com sucesso.');
    }

    public function archive(Request $request, Project $project): RedirectResponse
    {
        abort_unless($this->canManage($request, $project), 403);

        $project->update(['archived_at' => now(), 'is_active' => false]);
        ProjectActivity::record(
            $project,
            $request->user(),
            'project_archived',
            'Projeto arquivado',
            'project',
            $project->id,
        );

        return to_route('projects.index')->with('success', 'Projeto arquivado. O histórico permanece disponível.');
    }

    public function restore(Request $request, Project $project): RedirectResponse
    {
        abort_unless($this->canManage($request, $project), 403);

        $project->update(['archived_at' => null, 'is_active' => true]);
        ProjectActivity::record(
            $project,
            $request->user(),
            'project_restored',
            'Projeto restaurado',
            'project',
            $project->id,
        );

        return to_route('projects.show', $project)->with('success', 'Projeto restaurado com sucesso.');
    }

    /** @return array<string, mixed> */
    private function formOptions(?Project $project = null): array
    {
        return [
            'clients' => Client::query()
                ->where('is_active', true)
                ->when($project, fn ($query) => $query->orWhere('id', $project->client_id))
                ->orderBy('name')
                ->get(),
            'users' => User::query()
                ->where('is_active', true)
                ->whereHas('organizationMemberships', fn ($query) => $query
                    ->where('organization_id', app(\App\Services\OrganizationContext::class)->id())
                    ->where('status', \App\Enums\OrganizationMembershipStatus::Active->value))
                ->orderBy('name')
                ->get(),
            'levels' => ManagementLevel::options(),
            'executionNatures' => ExecutionNature::options(),
            'financialModes' => FinancialManagementMode::options(),
            'methodologies' => ProjectMethodology::options(),
            'originTypes' => collect(ProjectOriginType::cases())
                ->reject(fn (ProjectOriginType $type) => $type === ProjectOriginType::Initiative)
                ->mapWithKeys(fn (ProjectOriginType $type) => [$type->value => $type->label()])
                ->all(),
            'statuses' => ProjectStatus::options(),
        ];
    }

    /** @return array<string, string> */
    private function configurationSnapshot(Project $project): array
    {
        return [
            'execution_nature' => $project->execution_nature->label(),
            'financial_management_mode' => $project->financial_management_mode->label(),
            'management_level' => $project->management_level->label(),
            'methodology' => $project->methodologyLabel(),
        ];
    }

    private function configurationFieldLabel(string $field): string
    {
        return match ($field) {
            'execution_nature' => 'Natureza da execução',
            'financial_management_mode' => 'Tratamento financeiro',
            'management_level' => 'Nível de gestão',
            'methodology' => 'Metodologia',
            default => $field,
        };
    }

    /** @param array<string, mixed> $data */
    private function normalizeDates(array $data): array
    {
        if (in_array($data['status'], [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value], true)
            && blank($data['end_date'] ?? null)) {
            $data['end_date'] = today();
        }

        return $data;
    }

    private function ensureCanView(Request $request, Project $project): void
    {
        abort_unless($request->user()->canAccessProject($project), 403);
    }

    private function canManage(Request $request, Project $project): bool
    {
        return $request->user()->canManageProject($project);
    }

    private function organizationUsers()
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('organizationMemberships', fn ($query) => $query
                ->where('organization_id', app(\App\Services\OrganizationContext::class)->id())
                ->where('status', \App\Enums\OrganizationMembershipStatus::Active->value))
            ->orderBy('name')
            ->get();
    }

    private function activateManagerMembership(Project $project): void
    {
        $membership = ProjectMembership::firstOrNew([
            'project_id' => $project->id,
            'user_id' => $project->manager_id,
            'role' => ProjectRole::ProjectManager->value,
        ]);
        $membership->is_active = true;
        if (blank($membership->started_at)) {
            $membership->started_at = $project->start_date ?? today();
        }
        $membership->ended_at = null;
        $membership->save();
    }
}
