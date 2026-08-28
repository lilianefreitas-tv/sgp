<?php

namespace App\Services;

use App\Enums\ChangeRequestBaselineDisposition;
use App\Enums\ChangeRequestContractDisposition;
use App\Enums\ChangeRequestImplementationStatus;
use App\Enums\ChangeRequestState;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\ProjectActivity;
use App\Models\ProjectContract;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChangeRequestImplementationService
{
    public function __construct(
        private readonly ChangeRequestService $changeRequests,
        private readonly ProjectBaselineService $baselines,
        private readonly ProjectContractService $contracts,
    ) {}

    /** @param array<string, mixed> $data */
    public function saveDraft(ChangeRequest $changeRequest, array $data, User $actor): ChangeRequestImplementation
    {
        return DB::transaction(function () use ($changeRequest, $data, $actor): ChangeRequestImplementation {
            $lockedRequest = ChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            if ($lockedRequest->state !== ChangeRequestState::Approved) {
                throw ValidationException::withMessages([
                    'state' => 'Somente uma solicitação aprovada pode ser planejada ou implementada.',
                ]);
            }

            $implementation = $lockedRequest->implementation()->lockForUpdate()->first();
            if ($implementation?->status === ChangeRequestImplementationStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => 'A implementação concluída não pode ser alterada.',
                ]);
            }

            $responsibleId = isset($data['responsible_id'])
                ? (int) $data['responsible_id']
                : $implementation?->responsible_id;
            if ($implementation !== null
                && ! $actor->hasProjectRole(ProjectRole::ProjectManager, $lockedRequest->project)
                && $responsibleId !== $implementation->responsible_id) {
                throw ValidationException::withMessages([
                    'responsible_id' => 'Somente a gestão do projeto pode alterar o responsável pela implementação.',
                ]);
            }
            if ($responsibleId !== null) {
                $this->resolveResponsible($lockedRequest, $responsibleId);
            }

            $contractId = array_key_exists('contract_id', $data)
                ? (filled($data['contract_id']) ? (int) $data['contract_id'] : null)
                : $implementation?->contract_id;
            if ($contractId !== null) {
                $this->resolveContract($lockedRequest, $contractId);
            }

            $attributes = [
                'organization_id' => $lockedRequest->organization_id,
                'project_id' => $lockedRequest->project_id,
                'responsible_id' => $responsibleId,
                'plan_summary' => $this->draftText($data, 'plan_summary', $implementation?->plan_summary),
                'execution_summary' => $this->draftText($data, 'execution_summary', $implementation?->execution_summary),
                'verification_summary' => $this->draftText($data, 'verification_summary', $implementation?->verification_summary),
                'planned_start_date' => $data['planned_start_date'] ?? $implementation?->planned_start_date,
                'target_completion_date' => $data['target_completion_date'] ?? $implementation?->target_completion_date,
                'contract_disposition' => $data['contract_disposition'] ?? $implementation?->contract_disposition,
                'contract_id' => $contractId,
                'contract_justification' => $this->draftText($data, 'contract_justification', $implementation?->contract_justification),
                'amendment_reference' => $this->draftText($data, 'amendment_reference', $implementation?->amendment_reference),
                'amendment_summary' => $this->draftText($data, 'amendment_summary', $implementation?->amendment_summary),
                'amendment_effective_date' => $data['amendment_effective_date'] ?? $implementation?->amendment_effective_date,
                'baseline_disposition' => $data['baseline_disposition'] ?? $implementation?->baseline_disposition,
                'baseline_title' => $this->draftText($data, 'baseline_title', $implementation?->baseline_title),
                'baseline_justification' => $this->draftText($data, 'baseline_justification', $implementation?->baseline_justification),
                'updated_by' => $actor->id,
            ];

            if ($implementation === null) {
                $implementation = $lockedRequest->implementation()->create($attributes + [
                    'status' => ChangeRequestImplementationStatus::Planning,
                    'created_by' => $actor->id,
                ]);
                $eventType = 'plan_created';
            } else {
                $implementation->update($attributes);
                $eventType = 'plan_saved';
            }

            $this->event($implementation, $actor, $eventType, [
                'status' => $implementation->status->value,
                'fields' => array_values(array_intersect(array_keys($data), array_keys($attributes))),
            ]);
            ProjectActivity::record(
                $lockedRequest->project,
                $actor,
                'change_request_implementation_saved',
                'Planejamento da implementação salvo',
                'change_request',
                $lockedRequest->id,
                ['details' => $lockedRequest->code],
            );

            return $implementation->fresh(['responsible', 'contract', 'newBaseline', 'events.actor']);
        });
    }

    public function start(ChangeRequest $changeRequest, User $actor): ChangeRequestImplementation
    {
        return DB::transaction(function () use ($changeRequest, $actor): ChangeRequestImplementation {
            $lockedRequest = ChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            $implementation = $lockedRequest->implementation()->lockForUpdate()->firstOrFail();

            if ($lockedRequest->state !== ChangeRequestState::Approved
                || $implementation->status !== ChangeRequestImplementationStatus::Planning) {
                throw ValidationException::withMessages([
                    'status' => 'A implementação não está disponível para início.',
                ]);
            }

            $this->validateForStart($implementation);
            $implementation->update([
                'status' => ChangeRequestImplementationStatus::InProgress,
                'started_at' => now(),
                'planned_start_date' => $implementation->planned_start_date ?: today(),
                'updated_by' => $actor->id,
            ]);
            $this->event($implementation, $actor, 'implementation_started', [
                'responsible_id' => $implementation->responsible_id,
                'target_completion_date' => $implementation->target_completion_date?->toDateString(),
            ]);
            ProjectActivity::record(
                $lockedRequest->project,
                $actor,
                'change_request_implementation_started',
                'Implementação da mudança iniciada',
                'change_request',
                $lockedRequest->id,
                ['details' => $lockedRequest->code],
            );

            return $implementation->fresh(['responsible', 'events.actor']);
        });
    }

    public function complete(ChangeRequest $changeRequest, User $actor): ChangeRequestImplementation
    {
        return DB::transaction(function () use ($changeRequest, $actor): ChangeRequestImplementation {
            $lockedRequest = ChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            $implementation = $lockedRequest->implementation()->lockForUpdate()->firstOrFail();

            if ($lockedRequest->state !== ChangeRequestState::Approved
                || $implementation->status !== ChangeRequestImplementationStatus::InProgress) {
                throw ValidationException::withMessages([
                    'status' => 'A implementação deve estar em execução antes da conclusão.',
                ]);
            }

            $this->validateForCompletion($lockedRequest, $implementation);

            $amendmentVersion = null;
            if ($implementation->contract_disposition === ChangeRequestContractDisposition::AmendmentRegistered) {
                $contract = $this->resolveContract($lockedRequest, (int) $implementation->contract_id);
                $version = $this->contracts->registerAmendment($contract, [
                    'change_request_id' => $lockedRequest->id,
                    'change_request_code' => $lockedRequest->code,
                    'reference' => $implementation->amendment_reference,
                    'summary' => $implementation->amendment_summary,
                    'effective_date' => $implementation->amendment_effective_date?->toDateString(),
                ], $actor);
                $amendmentVersion = $version->version;
            }

            $baseline = null;
            if ($implementation->baseline_disposition === ChangeRequestBaselineDisposition::CreateNew) {
                $baseline = $this->baselines->createFromChange(
                    $lockedRequest,
                    (string) $implementation->baseline_title,
                    (string) $implementation->baseline_justification,
                    $actor,
                );
            }

            $implementation->update([
                'status' => ChangeRequestImplementationStatus::Completed,
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'amendment_contract_version' => $amendmentVersion,
                'new_baseline_id' => $baseline?->id,
                'updated_by' => $actor->id,
            ]);
            $this->event($implementation, $actor, 'implementation_completed', [
                'contract_disposition' => $implementation->contract_disposition->value,
                'contract_id' => $implementation->contract_id,
                'contract_version' => $amendmentVersion,
                'baseline_disposition' => $implementation->baseline_disposition->value,
                'new_baseline_id' => $baseline?->id,
                'new_baseline_version' => $baseline?->version,
            ]);

            $this->changeRequests->transition(
                $lockedRequest,
                ChangeRequestState::Implemented,
                $actor,
                'Implementação concluída: '.Str::limit((string) $implementation->execution_summary, 1000),
            );

            ProjectActivity::record(
                $lockedRequest->project,
                $actor,
                'change_request_implementation_completed',
                'Mudança implementada e encerrada',
                'change_request',
                $lockedRequest->id,
                ['details' => $lockedRequest->code, 'baseline_id' => $baseline?->id],
            );

            return $implementation->fresh(['responsible', 'completedBy', 'contract', 'newBaseline', 'events.actor']);
        });
    }

    private function validateForStart(ChangeRequestImplementation $implementation): void
    {
        $data = $this->validationData($implementation);
        $rules = [
            'responsible_id' => ['required', 'integer'],
            'plan_summary' => ['required', 'string'],
            'target_completion_date' => ['required', 'date'],
            'contract_disposition' => ['required'],
            'baseline_disposition' => ['required'],
            'contract_justification' => ['required_if:contract_disposition,not_applicable,no_amendment', 'nullable', 'string'],
            'contract_id' => ['required_if:contract_disposition,amendment_required,amendment_registered', 'nullable', 'integer'],
            'baseline_title' => ['required_if:baseline_disposition,create_new', 'nullable', 'string'],
            'baseline_justification' => ['required', 'string'],
        ];

        Validator::make($data, $rules, [], $this->validationAttributes())->validate();
    }

    private function validateForCompletion(
        ChangeRequest $changeRequest,
        ChangeRequestImplementation $implementation,
    ): void {
        $this->validateForStart($implementation);
        if ($implementation->contract_disposition === ChangeRequestContractDisposition::AmendmentRequired) {
            throw ValidationException::withMessages([
                'contract_disposition' => 'Formalize o aditivo ou registre que ele não é necessário antes de concluir.',
            ]);
        }

        Validator::make($this->validationData($implementation), [
            'execution_summary' => ['required', 'string'],
            'verification_summary' => ['required', 'string'],
            'amendment_reference' => ['required_if:contract_disposition,amendment_registered', 'nullable', 'string'],
            'amendment_summary' => ['required_if:contract_disposition,amendment_registered', 'nullable', 'string'],
            'amendment_effective_date' => ['required_if:contract_disposition,amendment_registered', 'nullable', 'date'],
        ], [], $this->validationAttributes())->validate();

        if (! $changeRequest->attachments()->where('attachment_kind', 'evidence')->exists()) {
            throw ValidationException::withMessages([
                'evidence' => 'Vincule ao menos uma evidência antes de concluir a implementação.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validationData(ChangeRequestImplementation $implementation): array
    {
        return [
            'responsible_id' => $implementation->responsible_id,
            'plan_summary' => $implementation->plan_summary,
            'execution_summary' => $implementation->execution_summary,
            'verification_summary' => $implementation->verification_summary,
            'target_completion_date' => $implementation->target_completion_date?->toDateString(),
            'contract_disposition' => $implementation->contract_disposition->value,
            'contract_id' => $implementation->contract_id,
            'contract_justification' => $implementation->contract_justification,
            'amendment_reference' => $implementation->amendment_reference,
            'amendment_summary' => $implementation->amendment_summary,
            'amendment_effective_date' => $implementation->amendment_effective_date?->toDateString(),
            'baseline_disposition' => $implementation->baseline_disposition->value,
            'baseline_title' => $implementation->baseline_title,
            'baseline_justification' => $implementation->baseline_justification,
        ];
    }

    /** @return array<string, string> */
    private function validationAttributes(): array
    {
        return [
            'responsible_id' => 'responsável pela implementação',
            'plan_summary' => 'plano de implementação',
            'execution_summary' => 'registro da execução',
            'verification_summary' => 'verificação da implementação',
            'target_completion_date' => 'data-alvo',
            'contract_disposition' => 'tratamento contratual',
            'contract_id' => 'contrato',
            'contract_justification' => 'justificativa contratual',
            'amendment_reference' => 'referência do aditivo',
            'amendment_summary' => 'descrição do aditivo',
            'amendment_effective_date' => 'vigência do aditivo',
            'baseline_disposition' => 'tratamento da baseline',
            'baseline_title' => 'título da nova baseline',
            'baseline_justification' => 'justificativa da baseline',
        ];
    }

    private function resolveResponsible(ChangeRequest $changeRequest, int $userId): User
    {
        $project = $changeRequest->project;
        $user = User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->whereHas('organizationMemberships', fn ($query) => $query
                ->where('organization_id', $project->organization_id)
                ->where('status', OrganizationMembershipStatus::Active->value)
                ->where('role_code', '!=', OrganizationRole::Reader->value))
            ->first();

        if ($user === null || ! $project->hasActiveMember($user)) {
            throw ValidationException::withMessages([
                'responsible_id' => 'Selecione uma pessoa ativa e vinculada ao projeto.',
            ]);
        }

        return $user;
    }

    private function resolveContract(ChangeRequest $changeRequest, int $contractId): ProjectContract
    {
        $contract = $changeRequest->project->contracts()->whereKey($contractId)->first();
        if ($contract === null) {
            throw ValidationException::withMessages([
                'contract_id' => 'O contrato selecionado não pertence ao projeto autorizado.',
            ]);
        }

        return $contract;
    }

    /** @param array<string, mixed> $metadata */
    private function event(
        ChangeRequestImplementation $implementation,
        User $actor,
        string $eventType,
        array $metadata,
    ): void {
        $implementation->events()->create([
            'organization_id' => $implementation->organization_id,
            'change_request_id' => $implementation->change_request_id,
            'event_type' => $eventType,
            'actor_id' => $actor->id,
            'metadata' => Arr::where($metadata, fn ($value) => $value !== null),
            'occurred_at' => now(),
        ]);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $data */
    private function draftText(array $data, string $key, ?string $current): ?string
    {
        return array_key_exists($key, $data)
            ? $this->nullableText($data[$key])
            : $current;
    }
}
