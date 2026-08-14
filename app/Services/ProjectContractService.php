<?php

namespace App\Services;

use App\Models\ProjectContract;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProjectContractService
{
    public function __construct(private OrganizationContext $context) {}

    public function create(array $data, User $actor): ProjectContract
    {
        return DB::transaction(function () use ($data, $actor): ProjectContract {
            $organizationId = $this->context->id();
            $number = ProjectContract::query()->lockForUpdate()->count() + 1;
            $contract = ProjectContract::create(Arr::except($data, ['attachments', 'reason']) + [
                'organization_id' => $organizationId, 'code' => sprintf('CTR-%06d', $number),
                'content' => $this->sanitize((string) ($data['content'] ?? '')), 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $this->version($contract, $actor, $data['reason'] ?? 'Registro inicial.');
            foreach ($data['attachments'] ?? [] as $file) {
                $this->attach($contract, $file, $actor);
            }

            return $contract;
        });
    }

    public function update(ProjectContract $contract, array $data, User $actor): ProjectContract
    {
        return DB::transaction(function () use ($contract, $data, $actor): ProjectContract {
            $contract->update(Arr::except($data, ['attachments', 'reason']) + ['content' => $this->sanitize((string) ($data['content'] ?? '')), 'updated_by' => $actor->id]);
            $this->version($contract, $actor, $data['reason'] ?? 'Atualização contratual.');
            foreach ($data['attachments'] ?? [] as $file) {
                $this->attach($contract, $file, $actor);
            }

            return $contract->fresh();
        });
    }

    private function version(ProjectContract $contract, User $actor, string $reason): void
    {
        $contract->versions()->create(['organization_id' => $contract->organization_id, 'version' => ($contract->versions()->max('version') ?? 0) + 1,
            'snapshot' => $contract->only(['code', 'title', 'contract_kind', 'entry_mode', 'status', 'contracting_party', 'contracted_party', 'object', 'content', 'external_reference', 'signed_at', 'start_date', 'end_date', 'amount', 'capacity_notes']),
            'reason' => $reason, 'created_by' => $actor->id]);
    }

    private function attach(ProjectContract $contract, UploadedFile $file, User $actor): void
    {
        $checksum = hash_file('sha256', $file->getRealPath());
        $stored = $file->hashName();
        $path = $file->storeAs("organizations/{$contract->organization_id}/contracts/{$contract->id}", $stored, 'local');
        $contract->attachments()->create(['organization_id' => $contract->organization_id, 'original_name' => $file->getClientOriginalName(), 'stored_name' => $stored,
            'disk' => 'local', 'path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(), 'checksum' => $checksum, 'created_by' => $actor->id]);
    }

    private function sanitize(string $html): string
    {
        $html = preg_replace('#<(script|style)[^>]*>.*?</\\1>#is', '', $html) ?? '';
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><blockquote><table><thead><tbody><tr><th><td>');

        return preg_replace('/<([a-z0-9]+)\\s+[^>]*>/i', '<$1>', $html) ?? '';
    }
}
