<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\StoreProjectAttachmentRequest;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Services\ProjectContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectAttachmentController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        ProjectContextService $contexts,
    ): View {
        $this->ensureCanView($request, $project);

        $attachments = $project->attachments()
            ->with('uploader')
            ->latest()
            ->paginate(15);
        $contexts->addLabels($project, $attachments);

        return view('attachments.index', [
            'project' => $project,
            'attachments' => $attachments,
            'contextOptions' => $contexts->options($project),
            'maxUploadMb' => config('sgp.attachments.max_kb') / 1024,
            'allowedExtensions' => config('sgp.attachments.allowed_extensions'),
        ]);
    }

    public function store(
        StoreProjectAttachmentRequest $request,
        Project $project,
        ProjectContextService $contexts,
    ): RedirectResponse {
        $this->ensureCanView($request, $project);
        $context = $contexts->resolve($project, $request->string('context')->toString());
        $file = $request->file('file');
        $disk = (string) config('sgp.storage.private_disk', 'local');
        $path = $file->store('project-attachments/'.$project->id, $disk);

        abort_if($path === false, 500, 'Não foi possível armazenar o arquivo.');

        try {
            DB::transaction(function () use ($request, $project, $context, $file, $disk, $path): void {
                $project->attachments()->create([
                    'uploaded_by' => $request->user()->id,
                    'context_type' => $context['type'],
                    'context_id' => $context['id'],
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'mime_type' => $file->getMimeType(),
                    'extension' => strtolower($file->getClientOriginalExtension()),
                    'size_bytes' => $file->getSize(),
                    'description' => $request->string('description')->trim()->toString() ?: null,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        return to_route('projects.attachments.index', $project)
            ->with('success', 'Arquivo anexado com sucesso.');
    }

    public function download(
        Request $request,
        Project $project,
        ProjectAttachment $attachment,
    ): StreamedResponse {
        $this->ensureCanView($request, $project);
        $this->ensureBelongsToProject($project, $attachment);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }

    public function destroy(
        Request $request,
        Project $project,
        ProjectAttachment $attachment,
    ): RedirectResponse {
        $this->ensureCanView($request, $project);
        $this->ensureBelongsToProject($project, $attachment);
        abort_unless($this->canRemove($request, $project, $attachment), 403);

        $attachment->forceFill(['deleted_by' => $request->user()->id])->save();
        $attachment->delete();

        return to_route('projects.attachments.index', $project)
            ->with('success', 'Anexo removido da consulta. O registro foi preservado no histórico.');
    }

    private function ensureBelongsToProject(Project $project, ProjectAttachment $attachment): void
    {
        abort_unless($attachment->project_id === $project->id, 404);
    }

    private function ensureCanView(Request $request, Project $project): void
    {
        abort_unless(
            $request->user()->isAdministrator() || $project->hasActiveMember($request->user()),
            403,
        );
    }

    private function canRemove(
        Request $request,
        Project $project,
        ProjectAttachment $attachment,
    ): bool {
        return $request->user()->isAdministrator()
            || $attachment->uploaded_by === $request->user()->id
            || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project);
    }
}
