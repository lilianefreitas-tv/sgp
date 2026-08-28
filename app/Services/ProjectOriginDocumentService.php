<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectOriginBaseline;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class ProjectOriginDocumentService
{
    public function __construct(
        private readonly OrganizationStoragePath $storagePaths,
        private readonly OrganizationAuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function store(Project $project, User $actor, UploadedFile $file, array $data): ProjectAttachment
    {
        $previous = null;
        if (filled($data['replaces_attachment_id'] ?? null)) {
            $previous = $project->originDocumentVersions()->whereKey($data['replaces_attachment_id'])->firstOrFail();
            if ($previous->origin_status === 'revoked') {
                throw new LogicException('Um documento revogado não pode receber nova versão.');
            }
        }

        $disk = (string) config('sgp.storage.private_disk', 'local');
        $temporaryPath = $file->getRealPath();
        if ($temporaryPath === false) {
            throw new LogicException('Não foi possível identificar o arquivo recebido.');
        }

        $sha256 = hash_file('sha256', $temporaryPath);
        if ($sha256 === false) {
            throw new LogicException('Não foi possível calcular a integridade do arquivo.');
        }

        $path = $file->store($this->storagePaths->projectBase($project).'/origin-documents', $disk);
        if ($path === false) {
            throw new LogicException('Não foi possível armazenar o documento de origem.');
        }

        try {
            $document = DB::transaction(function () use ($project, $actor, $file, $data, $previous, $disk, $path, $sha256): ProjectAttachment {
                if ($previous !== null) {
                    $project->originDocumentVersions()
                        ->where('origin_series_uuid', $previous->origin_series_uuid)
                        ->where('origin_status', 'current')
                        ->update(['origin_status' => 'historical']);
                }

                return $project->attachments()->create([
                    'uploaded_by' => $actor->id,
                    'context_type' => 'project_origin',
                    'context_id' => $project->id,
                    'is_origin_document' => true,
                    'origin_series_uuid' => $previous?->origin_series_uuid ?? (string) Str::uuid(),
                    'origin_category' => $data['origin_category'],
                    'origin_title' => $data['origin_title'],
                    'external_reference' => $data['external_reference'] ?: null,
                    'original_document_date' => $data['original_document_date'] ?: null,
                    'declared_version' => $data['declared_version'] ?: null,
                    'origin_version' => ($previous?->origin_version ?? 0) + 1,
                    'origin_status' => 'current',
                    'replaces_attachment_id' => $previous?->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'mime_type' => $file->getMimeType(),
                    'extension' => strtolower($file->getClientOriginalExtension()),
                    'size_bytes' => $file->getSize(),
                    'sha256' => $sha256,
                    'description' => $data['description'] ?: null,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        $this->audit->record('origin_document.upload', 'success', 'project_attachment', $document->id, [
            'project_id' => $project->id,
            'origin_series_uuid' => $document->origin_series_uuid,
            'origin_version' => $document->origin_version,
            'sha256' => $document->sha256,
        ]);

        return $document;
    }

    /** @param array<int, int|string> $documentIds */
    public function establishInitialBaseline(Project $project, User $actor, array $documentIds, ?string $purpose): ProjectOriginBaseline
    {
        if ($project->originBaseline()->exists()) {
            throw new LogicException('A referência inicial deste projeto já foi constituída.');
        }

        $documents = $project->originDocumentVersions()
            ->whereIn('id', $documentIds)
            ->where('origin_status', 'current')
            ->orderBy('id')
            ->get();

        if ($documents->count() !== count(array_unique(array_map('intval', $documentIds)))) {
            throw new LogicException('Selecione somente versões vigentes da documentação deste projeto.');
        }

        $canonical = $documents->map(fn (ProjectAttachment $document) => [
            'id' => $document->id,
            'series' => $document->origin_series_uuid,
            'version' => $document->origin_version,
            'sha256' => $document->sha256,
        ])->values()->all();
        $checksum = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $baseline = DB::transaction(function () use ($project, $actor, $documents, $purpose, $checksum): ProjectOriginBaseline {
            $baseline = $project->originBaseline()->create([
                'established_by' => $actor->id,
                'code' => $project->code.'-ORIGEM-01',
                'purpose' => filled($purpose) ? trim((string) $purpose) : null,
                'checksum' => $checksum,
                'established_at' => now(),
            ]);

            $baseline->documents()->attach($documents->mapWithKeys(fn (ProjectAttachment $document) => [
                $document->id => ['organization_id' => $project->organization_id, 'created_at' => now(), 'updated_at' => now()],
            ])->all());

            return $baseline;
        });

        $this->audit->record('origin_baseline.establish', 'success', 'project_origin_baseline', $baseline->id, [
            'project_id' => $project->id,
            'document_version_ids' => $documents->modelKeys(),
            'checksum' => $checksum,
        ]);

        return $baseline;
    }
}
