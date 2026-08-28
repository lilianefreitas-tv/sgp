<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChangeRequestAttachmentRequest;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Services\OrganizationAuditService;
use App\Services\OrganizationStoragePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChangeRequestAttachmentController extends Controller
{
    public function store(
        StoreChangeRequestAttachmentRequest $request,
        Project $project,
        ChangeRequest $changeRequest,
        OrganizationStoragePath $storagePaths,
        OrganizationAuditService $audit,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        $file = $request->file('file');
        $disk = (string) config('sgp.storage.private_disk', 'local');
        $temporaryPath = $file->getRealPath();
        abort_if($temporaryPath === false, 500, 'Não foi possível identificar o arquivo recebido.');
        $sha256 = hash_file('sha256', $temporaryPath);
        abort_if($sha256 === false, 500, 'Não foi possível identificar o arquivo recebido.');
        $path = $file->store($storagePaths->changeRequests($project, $changeRequest), $disk);
        abort_if($path === false, 500, 'Não foi possível armazenar o arquivo.');

        try {
            $attachment = DB::transaction(fn (): ProjectAttachment => $project->attachments()->create([
                'uploaded_by' => $request->user()->id,
                'context_type' => 'change_request',
                'context_id' => $changeRequest->id,
                'attachment_kind' => $request->string('attachment_kind')->toString(),
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'mime_type' => $file->getMimeType(),
                'extension' => strtolower($file->getClientOriginalExtension()),
                'size_bytes' => $file->getSize(),
                'sha256' => $sha256,
                'description' => $request->string('description')->trim()->toString() ?: null,
            ]));
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        $audit->record('change-request.attachment.upload', 'success', 'change_request', $changeRequest->id, [
            'project_id' => $project->id,
            'attachment_id' => $attachment->id,
            'kind' => $attachment->attachment_kind,
            'sha256' => $sha256,
        ]);

        return back()->with('success', 'Arquivo vinculado à solicitação.');
    }

    public function download(
        Request $request,
        Project $project,
        ChangeRequest $changeRequest,
        ProjectAttachment $attachment,
        OrganizationAuditService $audit,
    ): StreamedResponse {
        $this->ensureNested($project, $changeRequest);
        if (! $request->user()->can('view', $changeRequest)
            || ! $this->belongsToChangeRequest($project, $changeRequest, $attachment)) {
            $audit->record('change-request.attachment.download', 'denied', 'change_request', $changeRequest->id, [
                'project_id' => $project->id,
                'attachment_id' => $attachment->id,
            ]);
            abort(404);
        }

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);
        $audit->record('change-request.attachment.download', 'success', 'change_request', $changeRequest->id, [
            'project_id' => $project->id,
            'attachment_id' => $attachment->id,
            'sha256' => $attachment->sha256,
        ]);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(
        Request $request,
        Project $project,
        ChangeRequest $changeRequest,
        ProjectAttachment $attachment,
        OrganizationAuditService $audit,
    ): RedirectResponse {
        $this->ensureNested($project, $changeRequest);
        abort_unless($request->user()->can('manageAttachments', $changeRequest), 403);
        abort_unless($this->belongsToChangeRequest($project, $changeRequest, $attachment), 404);
        $attachment->forceFill(['deleted_by' => $request->user()->id])->save();
        $attachment->delete();
        $audit->record('change-request.attachment.remove', 'success', 'change_request', $changeRequest->id, [
            'project_id' => $project->id,
            'attachment_id' => $attachment->id,
            'path_preserved' => true,
        ]);

        return back()->with('success', 'Anexo removido da consulta, com registro histórico preservado.');
    }

    private function ensureNested(Project $project, ChangeRequest $changeRequest): void
    {
        abort_unless($changeRequest->project_id === $project->id, 404);
    }

    private function belongsToChangeRequest(
        Project $project,
        ChangeRequest $changeRequest,
        ProjectAttachment $attachment,
    ): bool {
        return $attachment->project_id === $project->id
            && $attachment->context_type === 'change_request'
            && $attachment->context_id === $changeRequest->id;
    }
}
