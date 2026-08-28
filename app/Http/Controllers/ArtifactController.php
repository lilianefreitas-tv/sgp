<?php

namespace App\Http\Controllers;

use App\Enums\ArtifactPublicationAudience;
use App\Enums\ArtifactPublicationMode;
use App\Enums\ArtifactType;
use App\Enums\ArtifactWorkflowState;
use App\Enums\DocumentRole;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Artifact;
use App\Models\DocumentRoleAssignment;
use App\Models\Initiative;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Services\ArtifactRevisionService;
use App\Services\ArtifactWorkflowService;
use App\Services\InitiativeDocumentService;
use App\Services\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;
use LogicException;

class ArtifactController extends Controller
{
    public function initiativeIndex(Initiative $initiative): View
    {
        return $this->index('initiative', $initiative);
    }

    public function projectIndex(Project $project): View
    {
        return $this->index('project', $project);
    }

    public function storeForInitiative(Request $request, Initiative $initiative, ArtifactRevisionService $service): RedirectResponse
    {
        return $this->store($request, $service, ['initiative_id' => $initiative->id], 'initiatives.artifacts.index', $initiative);
    }

    public function storeForProject(Request $request, Project $project, ArtifactRevisionService $service): RedirectResponse
    {
        return $this->store($request, $service, ['project_id' => $project->id], 'projects.artifacts.index', $project);
    }

    public function synchronizeInitiativeDossier(Request $request, Initiative $initiative, InitiativeDocumentService $documents): RedirectResponse
    {
        try {
            $artifact = $documents->synchronizeDossier($initiative, $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['dossier' => $exception->getMessage()]);
        }

        return to_route('artifacts.show', $artifact)
            ->with('success', 'Dossiê da iniciativa atualizado com os dados já registrados.');
    }

    public function show(Request $request, Artifact $artifact, ArtifactWorkflowService $workflow): View
    {
        $parentColumn = $artifact->initiative_id !== null ? 'initiative_id' : 'project_id';
        $parentId = $artifact->initiative_id ?? $artifact->project_id;
        $artifact->load([
            'initiative', 'project', 'revisions.changedBy',
            'workflowRounds' => fn ($query) => $query->with(['revision', 'submitter', 'decisions.actor'])->orderByDesc('sequence'),
            'publications' => fn ($query) => $query->with(['revision', 'publisher'])->orderByDesc('sequence'),
        ]);
        $assignments = DocumentRoleAssignment::query()->with('user')
            ->where('organization_id', $artifact->organization_id)
            ->where($parentColumn, $parentId)->whereNull('effective_until')->orderBy('role')->get();
        $members = OrganizationMembership::query()->with('user')->where('organization_id', $artifact->organization_id)
            ->where('status', OrganizationMembershipStatus::Active->value)->get()->pluck('user')->filter(fn ($user) => $user->is_active)->sortBy('name');
        $activeDocumentRoles = $request->user()->isSuperAdmin()
            ? collect(DocumentRole::cases())
            : $assignments->where('user_id', $request->user()->id)->pluck('role');

        return view('artifacts.show', [
            'artifact' => $artifact,
            'assignments' => $assignments,
            'members' => $members,
            'documentRoles' => DocumentRole::options(),
            'applicability' => $workflow->applicabilityFor($artifact),
            'latestApproved' => $workflow->latestApproved($artifact),
            'publicationModes' => ArtifactPublicationMode::options(),
            'publicationAudiences' => ArtifactPublicationAudience::options(),
            'publicationSections' => $artifact->revisions->firstWhere('sequence', $artifact->current_revision_sequence)?->content ?? [],
            'activeDocumentRoles' => $activeDocumentRoles,
        ]);
    }

    public function pending(Request $request, OrganizationContext $context): View
    {
        $user = $request->user();
        $assignments = DocumentRoleAssignment::query()
            ->where('organization_id', $context->id())
            ->where('user_id', $user->id)
            ->whereNull('effective_until')
            ->get();

        $reviewInitiatives = $assignments->where('role', DocumentRole::Reviewer)->pluck('initiative_id')->filter();
        $reviewProjects = $assignments->where('role', DocumentRole::Reviewer)->pluck('project_id')->filter();
        $approvalInitiatives = $assignments->where('role', DocumentRole::Approver)->pluck('initiative_id')->filter();
        $approvalProjects = $assignments->where('role', DocumentRole::Approver)->pluck('project_id')->filter();

        $artifacts = Artifact::query()
            ->with(['initiative', 'project'])
            ->where('organization_id', $context->id())
            ->whereNull('archived_at')
            ->where(function ($query) use ($user, $reviewInitiatives, $reviewProjects, $approvalInitiatives, $approvalProjects): void {
                if ($user->isSuperAdmin()) {
                    $query->whereIn('workflow_state', [ArtifactWorkflowState::InReview->value, ArtifactWorkflowState::AwaitingApproval->value]);

                    return;
                }
                $query->where(function ($review) use ($reviewInitiatives, $reviewProjects): void {
                    $review->where('workflow_state', ArtifactWorkflowState::InReview->value)
                        ->where(fn ($parent) => $parent->whereIn('initiative_id', $reviewInitiatives)->orWhereIn('project_id', $reviewProjects));
                })->orWhere(function ($approval) use ($approvalInitiatives, $approvalProjects): void {
                    $approval->where('workflow_state', ArtifactWorkflowState::AwaitingApproval->value)
                        ->where(fn ($parent) => $parent->whereIn('initiative_id', $approvalInitiatives)->orWhereIn('project_id', $approvalProjects));
                });
            })
            ->latest('updated_at')
            ->get();

        return view('artifacts.pending', compact('artifacts'));
    }

    public function revise(Request $request, Artifact $artifact, ArtifactRevisionService $service): RedirectResponse
    {
        $data = $request->validate($this->contentRules());
        try {
            $service->revise($artifact, $this->structuredContent($data), blank($data['metadata'] ?? null) ? null : $this->decodeJson($data['metadata']), $data['schema_version'], $data['change_reason'], $request->user());
        } catch (LogicException|JsonException $exception) {
            return back()->withErrors(['content' => $exception->getMessage()])->withInput();
        }

        return to_route('artifacts.show', $artifact)->with('success', 'Nova revisão registrada.');
    }

    public function archive(Request $request, Artifact $artifact, ArtifactRevisionService $service): RedirectResponse
    {
        $data = $request->validate(['archive_reason' => ['required', 'string', 'max:10000']]);
        try {
            $service->archive($artifact, $data['archive_reason'], $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['archive_reason' => $exception->getMessage()]);
        }

        return to_route('artifacts.show', $artifact)->with('success', 'Artefato arquivado.');
    }

    private function index(string $parentType, Initiative|Project $parent): View
    {
        $artifacts = $parent->artifacts()->withCount('revisions')->latest()->get();

        $artifactTypes = ArtifactType::optionsForParent($parentType);

        $dossier = $parentType === 'initiative'
            ? $artifacts->first(fn (Artifact $artifact) => $artifact->type === ArtifactType::InitiativeRecord && $artifact->archived_at === null)
            : null;

        return view('artifacts.index', compact('parentType', 'parent', 'artifacts', 'artifactTypes', 'dossier'));
    }

    /** @param array<string, int> $parent @param array<int|string, mixed> $routeParameters */
    private function store(Request $request, ArtifactRevisionService $service, array $parent, string $route, Initiative|Project $routeParameters): RedirectResponse
    {
        $allowedTypes = ArtifactType::optionsForParent($routeParameters instanceof Initiative ? 'initiative' : 'project');
        $data = $request->validate([...$this->contentRules(), 'type' => ['required', 'in:'.implode(',', array_keys($allowedTypes))], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000']]);
        try {
            $service->create([
                ...$parent,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'content' => $this->structuredContent($data),
                'metadata' => blank($data['metadata'] ?? null) ? null : $this->decodeJson($data['metadata']),
                'schema_version' => $data['schema_version'],
                'change_reason' => $data['change_reason'],
            ], $request->user());
        } catch (LogicException|JsonException $exception) {
            return back()->withErrors(['content' => $exception->getMessage()])->withInput();
        }

        return to_route($route, $routeParameters)->with('success', 'Artefato estruturado criado.');
    }

    /** @return array<mixed> */
    private function decodeJson(string $value): array
    {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new LogicException('O JSON deve conter um objeto ou lista.');
        }

        return $decoded;
    }

    /** @return array<string, array<int, string>> */
    private function contentRules(): array
    {
        return [
            'summary' => ['nullable', 'string', 'max:10000'],
            'objective' => ['nullable', 'string', 'max:10000'],
            'scope' => ['nullable', 'string', 'max:20000'],
            'body' => ['nullable', 'string', 'max:1048576'],
            'content' => ['nullable', 'string', 'max:1048576'],
            'metadata' => ['nullable', 'string', 'max:1048576'],
            'schema_version' => ['required', 'integer', 'min:1', 'max:65535'],
            'change_reason' => ['required', 'string', 'max:10000'],
        ];
    }

    /** @param array<string, mixed> $data @return array<mixed> */
    private function structuredContent(array $data): array
    {
        if (! blank($data['content'] ?? null)) {
            return $this->decodeJson($data['content']);
        }
        $content = array_filter([
            'resumo' => trim((string) ($data['summary'] ?? '')),
            'objetivo' => trim((string) ($data['objective'] ?? '')),
            'escopo' => trim((string) ($data['scope'] ?? '')),
            'conteudo' => trim((string) ($data['body'] ?? '')),
        ], fn (string $value) => $value !== '');
        if ($content === []) {
            throw new LogicException('Preencha ao menos um campo de conteúdo do artefato.');
        }

        return $content;
    }
}
