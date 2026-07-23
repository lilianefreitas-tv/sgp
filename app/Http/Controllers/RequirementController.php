<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Http\Requests\StoreRequirementRequest;
use App\Http\Requests\UpdateRequirementRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RequirementController extends Controller
{
    public function overview(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $priority = (string) $request->query('priority');
        $type = (string) $request->query('type');
        $activity = (string) $request->query('activity', 'active');

        $visibleProjects = Project::query()
            ->visibleTo($request->user())
            ->select('id');

        $requirements = Requirement::query()
            ->with(['project', 'responsible'])
            ->whereIn('project_id', $visibleProjects)
            ->search($search)
            ->when(array_key_exists($status, RequirementStatus::options()),
                fn ($query) => $query->where('status', $status))
            ->when(array_key_exists($priority, RequirementPriority::options()),
                fn ($query) => $query->where('priority', $priority))
            ->when(array_key_exists($type, RequirementType::options()),
                fn ($query) => $query->where('type', $type))
            ->when($activity === 'inactive',
                fn ($query) => $query->where('is_active', false),
                fn ($query) => $query->where('is_active', true))
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('requirements.overview', compact(
            'requirements',
            'search',
            'status',
            'priority',
            'type',
            'activity',
        ) + $this->options());
    }

    public function index(Request $request, Project $project): View
    {
        $this->ensureCanView($request, $project);

        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $priority = (string) $request->query('priority');
        $type = (string) $request->query('type');
        $activity = (string) $request->query('activity', 'active');

        $requirements = $project->requirements()
            ->with('responsible')
            ->search($search)
            ->when(array_key_exists($status, RequirementStatus::options()),
                fn ($query) => $query->where('status', $status))
            ->when(array_key_exists($priority, RequirementPriority::options()),
                fn ($query) => $query->where('priority', $priority))
            ->when(array_key_exists($type, RequirementType::options()),
                fn ($query) => $query->where('type', $type))
            ->when($activity === 'inactive',
                fn ($query) => $query->where('is_active', false),
                fn ($query) => $query->where('is_active', true))
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('requirements.index', compact(
            'project',
            'requirements',
            'search',
            'status',
            'priority',
            'type',
            'activity',
        ) + $this->options() + [
            'canManage' => $this->canManage($request, $project),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        abort_unless($this->canManage($request, $project), 403);

        return view('requirements.create', compact('project') + $this->formOptions($project));
    }

    public function store(StoreRequirementRequest $request, Project $project): RedirectResponse
    {
        $requirement = DB::transaction(function () use ($request, $project): Requirement {
            $project->newQuery()->whereKey($project->id)->lockForUpdate()->first();

            $requirement = $project->requirements()->create($request->validated());
            ProjectActivity::record(
                $project,
                $request->user(),
                'requirement_created',
                'Requisito cadastrado',
                'requirement',
                $requirement->id,
                ['details' => $requirement->code.' · '.$requirement->title],
            );

            return $requirement;
        });

        return to_route('projects.requirements.show', [$project, $requirement])
            ->with('success', 'Requisito cadastrado com sucesso.');
    }

    public function show(Request $request, Project $project, Requirement $requirement): View
    {
        $this->ensureBelongsToProject($project, $requirement);
        $this->ensureCanView($request, $project);

        $requirement->load(['responsible', 'versions.changedBy']);

        return view('requirements.show', compact('project', 'requirement') + [
            'canManage' => $this->canManage($request, $project),
        ]);
    }

    public function edit(Request $request, Project $project, Requirement $requirement): View
    {
        $this->ensureBelongsToProject($project, $requirement);
        abort_unless($this->canManage($request, $project), 403);

        return view('requirements.edit', compact('project', 'requirement') + $this->formOptions($project));
    }

    public function update(
        UpdateRequirementRequest $request,
        Project $project,
        Requirement $requirement,
    ): RedirectResponse {
        $this->ensureBelongsToProject($project, $requirement);

        DB::transaction(function () use ($request, $requirement): void {
            $validated = $request->validated();
            $reason = $validated['change_reason'] ?? null;
            unset($validated['change_reason']);

            $trackedFields = [
                'responsible_id',
                'title',
                'description',
                'type',
                'priority',
                'status',
                'acceptance_criteria',
                'source',
                'is_active',
            ];

            $requirement->fill($validated);

            if ($requirement->isDirty($trackedFields)) {
                RequirementVersion::create([
                    'requirement_id' => $requirement->id,
                    'version_number' => $requirement->current_version,
                    'title' => $requirement->getOriginal('title'),
                    'description' => $requirement->getOriginal('description'),
                    'acceptance_criteria' => $requirement->getOriginal('acceptance_criteria'),
                    'changed_by' => $request->user()->id,
                    'change_reason' => $reason,
                    'created_at' => now(),
                ]);

                $requirement->current_version = $requirement->current_version + 1;
                $requirement->save();
            }
        });

        return to_route('projects.requirements.show', [$project, $requirement])
            ->with('success', 'Requisito atualizado e versão anterior preservada.');
    }

    public function deactivate(Request $request, Project $project, Requirement $requirement): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $requirement);
        abort_unless($this->canManage($request, $project), 403);

        $this->changeActivity($request, $requirement, false, 'Requisito inativado.');

        return to_route('projects.requirements.index', $project)
            ->with('success', 'Requisito inativado. O histórico foi preservado.');
    }

    public function reactivate(Request $request, Project $project, Requirement $requirement): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $requirement);
        abort_unless($this->canManage($request, $project), 403);

        $this->changeActivity($request, $requirement, true, 'Requisito reativado.');

        return to_route('projects.requirements.show', [$project, $requirement])
            ->with('success', 'Requisito reativado com sucesso.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'statuses' => RequirementStatus::options(),
            'priorities' => RequirementPriority::options(),
            'types' => RequirementType::options(),
        ];
    }

    /** @return array<string, mixed> */
    private function formOptions(Project $project): array
    {
        return $this->options() + [
            'members' => $project->memberships()
                ->where('is_active', true)
                ->with('user')
                ->get()
                ->pluck('user')
                ->unique('id')
                ->sortBy('name')
                ->values(),
        ];
    }

    private function ensureBelongsToProject(Project $project, Requirement $requirement): void
    {
        abort_unless($requirement->project_id === $project->id, 404);
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
            || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project)
            || $request->user()->hasProjectRole(ProjectRole::RequirementsAnalyst, $project);
    }

    private function changeActivity(
        Request $request,
        Requirement $requirement,
        bool $isActive,
        string $reason,
    ): void {
        if ($requirement->is_active === $isActive) {
            return;
        }

        DB::transaction(function () use ($request, $requirement, $isActive, $reason): void {
            RequirementVersion::create([
                'requirement_id' => $requirement->id,
                'version_number' => $requirement->current_version,
                'title' => $requirement->title,
                'description' => $requirement->description,
                'acceptance_criteria' => $requirement->acceptance_criteria,
                'changed_by' => $request->user()->id,
                'change_reason' => $reason,
                'created_at' => now(),
            ]);

            $requirement->forceFill([
                'is_active' => $isActive,
                'current_version' => $requirement->current_version + 1,
            ])->save();
        });
    }
}
