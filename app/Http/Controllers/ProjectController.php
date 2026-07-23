<?php

namespace App\Http\Controllers;

use App\Enums\ManagementLevel;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMembership;
use App\Models\User;
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

        return view('projects.create', $this->formOptions());
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = DB::transaction(function () use ($request): Project {
            $data = $this->normalizeDates($request->validated());
            $project = Project::create($data);

            $this->activateManagerMembership($project);
            ProjectActivity::record(
                $project,
                $request->user(),
                'project_created',
                'Projeto cadastrado',
                'project',
                $project->id,
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
        $project->loadCount([
            'requirements as active_requirements_count' => fn ($query) => $query->where('is_active', true),
            'tasks as active_tasks_count' => fn ($query) => $query->where('is_active', true),
            'documents as documents_count',
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
                ? User::query()->where('is_active', true)->orderBy('name')->get()
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
            $project->update($this->normalizeDates($request->validated()));

            $this->activateManagerMembership($project);

            $changedFields = array_values(array_diff(
                array_keys($project->getChanges()),
                ['updated_at'],
            ));
            if ($changedFields !== []) {
                ProjectActivity::record(
                    $project,
                    $request->user(),
                    'project_updated',
                    'Informações do projeto atualizadas',
                    'project',
                    $project->id,
                    ['fields' => $changedFields],
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
                ->when($project, fn ($query) => $query->orWhere('id', $project->manager_id))
                ->orderBy('name')
                ->get(),
            'levels' => ManagementLevel::options(),
            'statuses' => ProjectStatus::options(),
        ];
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
        abort_unless(
            $request->user()->isAdministrator() || $project->hasActiveMember($request->user()),
            403,
        );
    }

    private function canManage(Request $request, Project $project): bool
    {
        return $request->user()->isAdministrator()
            || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project);
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
