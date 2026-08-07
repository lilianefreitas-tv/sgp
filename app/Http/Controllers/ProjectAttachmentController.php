<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\StoreProjectAttachmentRequest;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Services\OrganizationAuditService;
use App\Services\OrganizationStoragePath;
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
            'canContribute' => $request->user()->canContributeToProject($project),
        ]);
    }

    public function store(
        StoreProjectAttachmentRequest $request,
        Project $project,
        ProjectContextService $contexts,
        OrganizationStoragePath $storagePaths,
        OrganizationAuditService $audit,
    ): RedirectResponse {
        abort_unless($request->user()->canContributeToProject($project), 403);
        $context = $contexts->resolve($project, $request->string('context')->toString());
        $file = $request->file('file');
        $disk = (string) config('sgp.storage.private_disk', 'local');
        $temporaryPath = $file->getRealPath();
        abort_if($temporaryPath === false, 500, 'Não foi possível identificar o arquivo recebido.');
        $sha256 = hash_file('sha256', $temporaryPath);
        abort_if($sha256 === false, 500, 'Não foi possível identificar o arquivo recebido.');
        $path = $file->store($storagePaths->attachments($project), $disk);

        abort_if($path === false, 500, 'Não foi possível armazenar o arquivo.');

        try {
            $attachment = DB::transaction(function () use ($request, $project, $context, $file, $disk, $path, $sha256): ProjectAttachment {
                return $project->attachments()->create([
                    'uploaded_by' => $request->user()->id,
                    'context_type' => $context['type'],
                    'context_id' => $context['id'],
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'mime_type' => $file->getMimeType(),
                    'extension' => strtolower($file->getClientOriginalExtension()),
                    'size_bytes' => $file->getSize(),
                    'sha256' => $sha256,
                    'description' => $request->string('description')->trim()->toString() ?: null,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        $audit->record('attachment.upload', 'success', 'attachment', $attachment->id, [
            'project_id' => $project->id,
            'disk' => $disk,
            'path' => $path,
            'sha256' => $sha256,
        ]);

        return to_route('projects.attachments.index', $project)
            ->with('success', 'Arquivo anexado com sucesso.');
    }

    public function download(
        Request $request,
        Project $project,
        ProjectAttachment $attachment,
        OrganizationAuditService $audit,
    ): StreamedResponse {
        if (! $this->canView($request, $project)
            || $attachment->project_id !== $project->id) {
            $audit->record('attachment.download', 'denied', 'attachment', $attachment->id, [
                'project_id' => $project->id,
                'reason' => 'project_authorization',
                'target_organization_id' => (int) $attachment->organization_id,
            ]);

            abort(404);
        }

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            $audit->record('attachment.download', 'failed', 'attachment', $attachment->id, [
                'project_id' => $project->id,
                'reason' => 'file_missing',
            ]);

            abort(404);
        }

        $audit->record('attachment.download', 'success', 'attachment', $attachment->id, [
            'project_id' => $project->id,
            'sha256' => $attachment->sha256,
        ]);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }

    public function destroy(
        Request $request,
        Project $project,
        ProjectAttachment $attachment,
        OrganizationAuditService $audit,
    ): RedirectResponse {
        $this->ensureCanView($request, $project);
        $this->ensureBelongsToProject($project, $attachment);
        abort_unless($this->canRemove($request, $project, $attachment), 403);

        $attachment->forceFill(['deleted_by' => $request->user()->id])->save();
        $attachment->delete();

        $audit->record('attachment.remove', 'success', 'attachment', $attachment->id, [
            'project_id' => $project->id,
            'path_preserved' => true,
        ]);

        return to_route('projects.attachments.index', $project)
            ->with('success', 'Anexo removido da consulta. O registro foi preservado no histórico.');
    }

    private function ensureBelongsToProject(Project $project, ProjectAttachment $attachment): void
    {
        abort_unless($attachment->project_id === $project->id, 404);
    }

    private function ensureCanView(Request $request, Project $project): void
    {
        abort_unless($this->canView($request, $project), 403);
    }

    private function canView(Request $request, Project $project): bool
    {
        return $request->user()->canAccessProject($project);
    }

    private function canRemove(
        Request $request,
        Project $project,
        ProjectAttachment $attachment,
    ): bool {
        return $request->user()->administersCurrentOrganization()
            || $attachment->uploaded_by === $request->user()->id
            || $request->user()->hasProjectRole(ProjectRole::ProjectManager, $project);
    }
}
