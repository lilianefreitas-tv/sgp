<?php

namespace App\Services;

use App\Models\ProjectContract;
use App\Models\ProjectContractVersion;
use App\Models\Initiative;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProjectContractService
{
    public function __construct(
        private OrganizationContext $context,
        private OrganizationAuditService $audit,
    ) {}

    public function create(array $data, User $actor): ProjectContract
    {
        return DB::transaction(function () use ($data, $actor): ProjectContract {
            $organizationId = $this->context->id();
            DB::table('organizations')->where('id', $organizationId)->lockForUpdate()->firstOrFail();
            [$initiativeId, $projectId] = $this->resolveRelations(
                $data['initiative_id'] ?? null,
                $data['project_id'] ?? null,
            );
            $number = ProjectContract::query()->count() + 1;
            $contract = ProjectContract::create(Arr::except($data, ['attachments', 'reason', 'initiative_id', 'project_id', 'content']) + [
                'organization_id' => $organizationId, 'code' => sprintf('CTR-%06d', $number),
                'initiative_id' => $initiativeId, 'project_id' => $projectId,
                'content' => $this->sanitize((string) ($data['content'] ?? '')), 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $this->version($contract, $actor, $data['reason'] ?? 'Registro inicial.');
            foreach ($data['attachments'] ?? [] as $file) {
                $this->attach($contract, $file, $actor);
            }
            if ($projectId !== null) {
                $this->recordProjectLink($contract, Project::query()->findOrFail($projectId), $actor, 'contract_created_for_project');
            }

            return $contract;
        });
    }

    public function update(ProjectContract $contract, array $data, User $actor): ProjectContract
    {
        return DB::transaction(function () use ($contract, $data, $actor): ProjectContract {
            unset($data['initiative_id'], $data['project_id']);
            $contract->update(Arr::except($data, ['attachments', 'reason', 'content']) + ['content' => $this->sanitize((string) ($data['content'] ?? '')), 'updated_by' => $actor->id]);
            $this->version($contract, $actor, $data['reason'] ?? 'Atualização contratual.');
            foreach ($data['attachments'] ?? [] as $file) {
                $this->attach($contract, $file, $actor);
            }

            return $contract->fresh();
        });
    }

    public function linkToProject(ProjectContract $contract, Project $project, User $actor, string $reason = 'Contrato vinculado ao projeto existente.'): ProjectContract
    {
        return DB::transaction(function () use ($contract, $project, $actor, $reason): ProjectContract {
            $lockedContract = ProjectContract::query()->lockForUpdate()->findOrFail($contract->id);
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ((int) $lockedContract->organization_id !== (int) $lockedProject->organization_id) {
                throw new LogicException('Contrato e projeto devem pertencer à mesma organização.');
            }
            if ($lockedContract->project_id !== null) {
                if ((int) $lockedContract->project_id === (int) $lockedProject->id) {
                    return $lockedContract;
                }

                throw new LogicException('O contrato já está vinculado a outro projeto e não pode ser transferido silenciosamente.');
            }

            $projectInitiativeId = $lockedProject->initiative_id !== null ? (int) $lockedProject->initiative_id : null;
            $contractInitiativeId = $lockedContract->initiative_id !== null ? (int) $lockedContract->initiative_id : null;
            if ($contractInitiativeId !== null && $projectInitiativeId !== $contractInitiativeId) {
                throw new LogicException('A iniciativa do contrato não corresponde à origem do projeto selecionado.');
            }

            $lockedContract->update([
                'project_id' => $lockedProject->id,
                'initiative_id' => $projectInitiativeId,
                'updated_by' => $actor->id,
            ]);
            $this->version($lockedContract, $actor, $reason);
            $this->recordProjectLink($lockedContract, $lockedProject, $actor, 'contract_linked');

            return $lockedContract->fresh(['initiative', 'project', 'versions']);
        });
    }

    public function linkToInitiative(
        ProjectContract $contract,
        Initiative $initiative,
        User $actor,
        string $reason = 'Contrato vinculado à iniciativa existente.',
        ?int $expectedLockVersion = null,
    ): ProjectContract {
        return DB::transaction(function () use ($contract, $initiative, $actor, $reason, $expectedLockVersion): ProjectContract {
            $lockedContract = ProjectContract::query()->lockForUpdate()->findOrFail($contract->id);
            $lockedInitiative = Initiative::query()->lockForUpdate()->findOrFail($initiative->id);

            if ((int) $lockedContract->organization_id !== (int) $lockedInitiative->organization_id
                || (int) $this->context->id() !== (int) $lockedInitiative->organization_id) {
                throw new LogicException('Contrato e iniciativa devem pertencer ao contexto organizacional ativo.');
            }
            if (! $actor->is_active
                || ((int) $lockedInitiative->created_by !== (int) $actor->id && ! $actor->canCreateProjects())) {
                throw new LogicException('O ator não possui autorização para vincular contratos a esta iniciativa.');
            }
            if ($expectedLockVersion !== null && (int) $lockedInitiative->lock_version !== $expectedLockVersion) {
                throw new LogicException('A iniciativa foi alterada por outra pessoa. Recarregue a página antes de continuar.');
            }
            if ($lockedInitiative->project()->exists() || in_array($lockedInitiative->state, [
                \App\Enums\InitiativeState::Converted,
                \App\Enums\InitiativeState::Cancelled,
                \App\Enums\InitiativeState::Archived,
            ], true)) {
                throw new LogicException('A iniciativa não aceita novos vínculos contratuais no estado atual.');
            }
            if ($lockedContract->project_id !== null) {
                throw new LogicException('Um contrato já vinculado a projeto não pode ser transferido para uma iniciativa.');
            }
            if ($lockedContract->initiative_id !== null) {
                if ((int) $lockedContract->initiative_id === (int) $lockedInitiative->id) {
                    return $lockedContract;
                }

                throw new LogicException('O contrato já está vinculado a outra iniciativa e não pode ser transferido silenciosamente.');
            }

            $lockedContract->update(['initiative_id' => $lockedInitiative->id, 'updated_by' => $actor->id]);
            $lockedInitiative->update(['lock_version' => $lockedInitiative->lock_version + 1]);
            $this->version($lockedContract, $actor, $reason);
            $this->audit->record('initiative.contract_linked', 'success', Initiative::class, $lockedInitiative->id, [
                'initiative_code' => $lockedInitiative->code,
                'contract_id' => $lockedContract->id,
                'contract_code' => $lockedContract->code,
                'reason' => $reason,
            ], (int) $lockedInitiative->organization_id, $actor);

            return $lockedContract->fresh(['initiative', 'project', 'versions']);
        });
    }

    /** @param array<string, mixed> $amendment */
    public function registerAmendment(
        ProjectContract $contract,
        array $amendment,
        User $actor,
    ): ProjectContractVersion {
        return DB::transaction(function () use ($contract, $amendment, $actor): ProjectContractVersion {
            $locked = ProjectContract::query()->lockForUpdate()->findOrFail($contract->id);
            $version = ((int) $locked->versions()->max('version')) + 1;

            return $locked->versions()->create([
                'organization_id' => $locked->organization_id,
                'version' => $version,
                'snapshot' => $locked->only([
                    'initiative_id',
                    'project_id',
                    'code',
                    'title',
                    'contract_kind',
                    'entry_mode',
                    'status',
                    'contracting_party',
                    'contracted_party',
                    'object',
                    'content',
                    'external_reference',
                    'signed_at',
                    'start_date',
                    'end_date',
                    'amount',
                    'capacity_notes',
                ]) + ['amendment' => $amendment],
                'reason' => 'Aditivo vinculado à '.($amendment['change_request_code'] ?? 'solicitação de mudança').'.',
                'created_by' => $actor->id,
            ]);
        });
    }

    private function version(ProjectContract $contract, User $actor, string $reason): void
    {
        $contract->versions()->create(['organization_id' => $contract->organization_id, 'version' => ($contract->versions()->max('version') ?? 0) + 1,
            'snapshot' => $contract->only(['initiative_id', 'project_id', 'code', 'title', 'contract_kind', 'entry_mode', 'status', 'contracting_party', 'contracted_party', 'object', 'content', 'external_reference', 'signed_at', 'start_date', 'end_date', 'amount', 'capacity_notes']),
            'reason' => $reason, 'created_by' => $actor->id]);
    }

    /** @return array{0: int|null, 1: int|null} */
    private function resolveRelations(mixed $initiativeId, mixed $projectId): array
    {
        $project = filled($projectId) ? Project::query()->findOrFail((int) $projectId) : null;
        $initiative = filled($initiativeId) ? Initiative::query()->findOrFail((int) $initiativeId) : null;

        if ($project !== null) {
            $derivedInitiativeId = $project->initiative_id !== null ? (int) $project->initiative_id : null;
            if ($initiative !== null && (int) $initiative->id !== $derivedInitiativeId) {
                throw new LogicException('A iniciativa informada não corresponde à origem do projeto selecionado.');
            }

            return [$derivedInitiativeId, (int) $project->id];
        }

        return [$initiative?->id, null];
    }

    private function recordProjectLink(ProjectContract $contract, Project $project, User $actor, string $eventType): void
    {
        ProjectActivity::record(
            $project,
            $actor,
            $eventType,
            'Contrato '.$contract->code.' vinculado ao projeto.',
            'project_contract',
            $contract->id,
            ['contract_code' => $contract->code, 'initiative_id' => $contract->initiative_id],
        );
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
