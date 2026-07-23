<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\ProjectRole;
use App\Http\Requests\GenerateDocumentRequest;
use App\Http\Requests\UpdateProjectDocumentationRequest;
use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function overview(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $projects = Project::query()
            ->visibleTo($request->user())
            ->whereNull('archived_at')
            ->with(['client', 'manager'])
            ->withCount('documents')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('code', "%{$search}%", caseSensitive: false);
            }))
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('documents.overview', compact('projects', 'search'));
    }

    public function index(Request $request, Project $project): View
    {
        $this->ensureCanView($request, $project);

        $project->load(['client', 'manager']);
        $documents = $project->documents()
            ->with(['template', 'generator'])
            ->latest('generated_at')
            ->paginate(12);
        $templates = DocumentTemplate::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderByDesc('version')
            ->get()
            ->groupBy(fn (DocumentTemplate $template) => $template->type->value);

        return view('documents.index', [
            'project' => $project,
            'documents' => $documents,
            'templates' => $templates,
            'types' => DocumentType::cases(),
            'canGenerate' => $this->canGenerate($request, $project),
            'visionReady' => $this->visionReady($project),
        ]);
    }

    public function editSetup(Request $request, Project $project): View
    {
        abort_unless($this->canGenerate($request, $project), 403);

        return view('documents.setup', compact('project'));
    }

    public function updateSetup(UpdateProjectDocumentationRequest $request, Project $project): RedirectResponse
    {
        abort_unless($this->canGenerate($request, $project), 403);

        $project->update($request->validated());

        return to_route('projects.documents.index', $project)
            ->with('success', 'Informações documentais atualizadas com sucesso.');
    }

    public function generate(
        GenerateDocumentRequest $request,
        Project $project,
        DocumentGenerationService $generator,
    ): RedirectResponse {
        abort_unless($this->canGenerate($request, $project), 403);

        $template = DocumentTemplate::query()
            ->whereKey($request->integer('document_template_id'))
            ->where('is_active', true)
            ->firstOrFail();

        if ($template->type === DocumentType::Vision && ! $this->visionReady($project)) {
            return to_route('projects.documents.setup.edit', $project)
                ->with('warning', 'Complete as informações documentais antes de gerar o Documento de Visão.');
        }

        DB::transaction(function () use ($request, $project, $template, $generator): void {
            // Serializa a geração de documentos do mesmo projeto sem aplicar
            // FOR UPDATE à consulta agregada, operação não permitida no PostgreSQL.
            Project::query()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lastVersion = ProjectDocument::query()
                ->where('project_id', $project->id)
                ->where('type', $template->type->value)
                ->max('version');

            $generator->generate(
                $project,
                $template,
                $request->user(),
                ((int) $lastVersion) + 1,
            );
        });

        return to_route('projects.documents.index', $project)
            ->with('success', $template->type->label().' gerado em DOCX e PDF.');
    }

    public function download(
        Request $request,
        Project $project,
        ProjectDocument $document,
        string $format,
    ): StreamedResponse {
        $this->ensureCanView($request, $project);
        abort_unless($document->project_id === $project->id, 404);
        abort_unless(in_array($format, ['docx', 'pdf'], true), 404);

        $path = $format === 'docx' ? $document->docx_path : $document->pdf_path;
        $fileName = $format === 'docx' ? $document->docx_file_name : $document->pdf_file_name;
        abort_unless(Storage::disk('local')->exists($path), 404, 'Arquivo não encontrado no armazenamento.');

        return Storage::disk('local')->download($path, $fileName);
    }

    private function ensureCanView(Request $request, Project $project): void
    {
        abort_unless(
            $request->user()->isAdministrator() || $project->hasActiveMember($request->user()),
            403,
        );
    }

    private function canGenerate(Request $request, Project $project): bool
    {
        return $request->user()->isAdministrator()
            || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project)
            || $request->user()->hasProjectRole(ProjectRole::RequirementsAnalyst, $project);
    }

    private function visionReady(Project $project): bool
    {
        return filled($project->document_context)
            && filled($project->problem_statement)
            && filled($project->solution_summary)
            && filled($project->target_audience)
            && filled($project->scope_included);
    }
}
