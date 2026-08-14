<?php

namespace App\Http\Controllers;

use App\Enums\OriginDocumentCategory;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Services\ProjectOriginDocumentService;
use App\Services\ProjectOriginEvolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

class ProjectOriginDocumentController extends Controller
{
    public function index(Request $request, Project $project, ProjectOriginEvolutionService $evolution): View
    {
        abort_unless($request->user()->canAccessProject($project), 403);

        $versions = $project->originDocumentVersions()
            ->with('uploader')
            ->orderBy('origin_title')
            ->orderByDesc('origin_version')
            ->get();

        $series = $versions->groupBy('origin_series_uuid');
        $baseline = $project->originBaseline()->with(['establishedBy', 'documents'])->first();

        return view('projects.origin-documents.index', [
            'project' => $project,
            'series' => $series,
            'currentVersions' => $versions->where('origin_status', 'current'),
            'baseline' => $baseline,
            'evolution' => $evolution->summarize($project),
            'categories' => OriginDocumentCategory::options(),
            'allowedExtensions' => config('sgp.attachments.allowed_extensions'),
            'maxUploadMb' => config('sgp.attachments.max_kb') / 1024,
            'canContribute' => $request->user()->canContributeToProject($project),
            'canManage' => $request->user()->administersCurrentOrganization()
                || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project),
        ]);
    }

    public function store(Request $request, Project $project, ProjectOriginDocumentService $documents): RedirectResponse
    {
        abort_unless($request->user()->canContributeToProject($project), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('sgp.attachments.max_kb'), 'extensions:'.implode(',', config('sgp.attachments.allowed_extensions'))],
            'origin_category' => ['required', Rule::enum(OriginDocumentCategory::class)],
            'origin_title' => ['required', 'string', 'max:200'],
            'external_reference' => ['nullable', 'string', 'max:150'],
            'original_document_date' => ['nullable', 'date'],
            'declared_version' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:300'],
            'replaces_attachment_id' => [
                'nullable',
                Rule::exists('project_attachments', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $project->organization_id)
                    ->where('project_id', $project->id)
                    ->where('is_origin_document', true)),
            ],
        ]);

        $documents->store($project, $request->user(), $request->file('file'), $data);

        return to_route('projects.origin-documents.index', $project)->with('success', 'Documento de origem registrado com integridade e histórico de versão.');
    }

    public function establishBaseline(Request $request, Project $project, ProjectOriginDocumentService $documents): RedirectResponse
    {
        $canManage = $request->user()->administersCurrentOrganization()
            || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project);
        abort_unless($canManage, 403);

        $data = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer'],
            'purpose' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $documents->establishInitialBaseline($project, $request->user(), $data['document_ids'], $data['purpose'] ?? null);
        } catch (LogicException $exception) {
            return back()->withErrors(['baseline' => $exception->getMessage()]);
        }

        return to_route('projects.origin-documents.index', $project)->with('success', 'Referência inicial constituída. O SGP acompanhará o projeto a partir deste ponto.');
    }
}
