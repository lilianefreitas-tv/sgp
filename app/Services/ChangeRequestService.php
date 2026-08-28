<?php

namespace App\Services;

use App\Enums\ChangeRequestAnalysisStatus;
use App\Enums\ChangeRequestImplementationStatus;
use App\Enums\ChangeRequestState;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\ProjectRole;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChangeRequestService
{
    /** @var array<string, list<ChangeRequestState>> */
    private const INITIAL_TRANSITIONS = [
        'draft' => [ChangeRequestState::Submitted, ChangeRequestState::Cancelled],
        'submitted' => [ChangeRequestState::UnderAnalysis, ChangeRequestState::Returned, ChangeRequestState::Cancelled],
        'under_analysis' => [
            ChangeRequestState::Returned,
            ChangeRequestState::Approved,
            ChangeRequestState::Rejected,
            ChangeRequestState::Cancelled,
        ],
        'returned' => [ChangeRequestState::Submitted, ChangeRequestState::Cancelled],
        'approved' => [ChangeRequestState::Implemented],
    ];

    public function create(Project $project, array $data, User $actor): ChangeRequest
    {
        return DB::transaction(function () use ($project, $data, $actor): ChangeRequest {
            DB::table('projects')
                ->where('id', $project->id)
                ->where('organization_id', $project->organization_id)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence = (int) $project->changeRequests()->max('sequence') + 1;
            $requester = $this->resolveProjectUser($project, $actor->id, 'requester_id');
            $baselineId = $this->resolveBaseline($project, $data['baseline_id'] ?? null);

            $changeRequest = $project->changeRequests()->create([
                'organization_id' => $project->organization_id,
                'sequence' => $sequence,
                'code' => 'RM-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                'origin' => $data['origin'],
                'title' => trim($data['title']),
                'description' => $this->nullableText($data['description'] ?? null),
                'justification' => $this->nullableText($data['justification'] ?? null),
                'urgency' => $data['urgency'] ?? null,
                'baseline_id' => $baselineId,
                'requester_id' => $requester->id,
                'analyst_id' => null,
                'state' => ChangeRequestState::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncAffectedItems($changeRequest, $data['affected'] ?? []);
            $changeRequest->transitions()->create([
                'organization_id' => $project->organization_id,
                'from_state' => null,
                'to_state' => ChangeRequestState::Draft,
                'actor_id' => $actor->id,
                'reason' => 'Solicitação registrada como rascunho.',
                'metadata' => ['source' => 'P07.1'],
                'occurred_at' => now(),
            ]);
            ProjectActivity::record(
                $project,
                $actor,
                'change_request_created',
                'Solicitação de mudança registrada',
                'change_request',
                $changeRequest->id,
                ['details' => $changeRequest->code.' · '.$changeRequest->title],
            );

            return $changeRequest->load(['requester', 'analyst', 'baseline', 'affectedItems', 'transitions.actor']);
        });
    }

    public function update(ChangeRequest $changeRequest, array $data, User $actor): ChangeRequest
    {
        return DB::transaction(function () use ($changeRequest, $data, $actor): ChangeRequest {
            $locked = ChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            if (! $locked->state->isEditable()) {
                throw ValidationException::withMessages([
                    'state' => 'A solicitação não pode ser editada no estado atual.',
                ]);
            }

            $project = $locked->project;
            $locked->update([
                'origin' => $data['origin'],
                'title' => trim($data['title']),
                'description' => $this->nullableText($data['description'] ?? null),
                'justification' => $this->nullableText($data['justification'] ?? null),
                'urgency' => $data['urgency'] ?? null,
                'baseline_id' => $this->resolveBaseline($project, $data['baseline_id'] ?? null),
                'requester_id' => $locked->requester_id,
                'analyst_id' => $locked->analyst_id,
                'updated_by' => $actor->id,
            ]);
            $this->syncAffectedItems($locked, $data['affected'] ?? []);

            ProjectActivity::record(
                $project,
                $actor,
                'change_request_updated',
                'Solicitação de mudança atualizada',
                'change_request',
                $locked->id,
                ['details' => $locked->code.' · '.$locked->title],
            );

            return $locked->fresh()->load(['requester', 'analyst', 'baseline', 'affectedItems', 'transitions.actor']);
        });
    }

    public function transition(
        ChangeRequest $changeRequest,
        ChangeRequestState $target,
        User $actor,
        ?string $reason = null,
    ): ChangeRequest {
        return DB::transaction(function () use ($changeRequest, $target, $actor, $reason): ChangeRequest {
            $locked = ChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            $from = $locked->state;
            $allowed = self::INITIAL_TRANSITIONS[$from->value] ?? [];

            if (! in_array($target, $allowed, true)) {
                throw ValidationException::withMessages([
                    'state' => "A transição de {$from->label()} para {$target->label()} não está disponível no fluxo atual.",
                ]);
            }

            $decisionAnalysis = null;
            if (in_array($target, [ChangeRequestState::Approved, ChangeRequestState::Rejected], true)) {
                $decisionAnalysis = $locked->impactAnalyses()->latest('round')->first();
                if ($decisionAnalysis?->status !== ChangeRequestAnalysisStatus::Completed) {
                    throw ValidationException::withMessages([
                        'analysis' => 'Conclua a análise de impacto antes de registrar a decisão final.',
                    ]);
                }
            }

            $implementation = null;
            if ($target === ChangeRequestState::Implemented) {
                $implementation = $locked->implementation()->first();
                if ($implementation?->status !== ChangeRequestImplementationStatus::Completed) {
                    throw ValidationException::withMessages([
                        'implementation' => 'Conclua e verifique a implementação antes de encerrar a solicitação.',
                    ]);
                }
            }

            $reason = $this->nullableText($reason);
            if (in_array($target, [
                ChangeRequestState::Returned,
                ChangeRequestState::Approved,
                ChangeRequestState::Rejected,
                ChangeRequestState::Cancelled,
                ChangeRequestState::Implemented,
            ], true)
                && $reason === null) {
                throw ValidationException::withMessages([
                    'reason' => 'Informe o parecer ou motivo para preservar a rastreabilidade da transição.',
                ]);
            }

            if ($target === ChangeRequestState::Submitted) {
                $this->validateSubmission($locked);
            }

            if ($target === ChangeRequestState::UnderAnalysis) {
                $this->resolveRequiredAnalyst($locked->project, $actor->id);
                if ($locked->analyst_id !== null && $locked->analyst_id !== $actor->id) {
                    throw ValidationException::withMessages([
                        'analyst_id' => 'A solicitação foi designada para outra pessoa. Somente ela pode iniciar a própria análise.',
                    ]);
                }
                $locked->analyst_id = $actor->id;
            }

            $now = now();
            $locked->state = $target;
            $locked->updated_by = $actor->id;
            if ($target === ChangeRequestState::Submitted) {
                $locked->submitted_at = $now;
            } elseif ($target === ChangeRequestState::UnderAnalysis) {
                $locked->analysis_started_at = $now;
            } elseif ($target === ChangeRequestState::Returned) {
                $locked->returned_at = $now;
            } elseif ($target === ChangeRequestState::Cancelled) {
                $locked->cancelled_at = $now;
            } elseif ($target === ChangeRequestState::Implemented) {
                $locked->implemented_at = $now;
            }
            $locked->save();

            $analysisRound = null;
            if ($target === ChangeRequestState::UnderAnalysis) {
                $analysisRound = $locked->impactAnalyses()->create([
                    'organization_id' => $locked->organization_id,
                    'round' => ((int) $locked->impactAnalyses()->max('round')) + 1,
                    'analyst_id' => $locked->analyst_id,
                    'status' => ChangeRequestAnalysisStatus::Draft,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $metadata = ['source' => 'P07.1'];
            if ($analysisRound !== null) {
                $metadata = [
                    'source' => 'P07.2',
                    'analysis_id' => $analysisRound->id,
                    'analysis_round' => $analysisRound->round,
                ];
            } elseif ($decisionAnalysis !== null) {
                $metadata = [
                    'source' => 'P07.2',
                    'analysis_id' => $decisionAnalysis->id,
                    'analysis_round' => $decisionAnalysis->round,
                    'recommendation' => $decisionAnalysis->recommendation->value,
                ];
            } elseif ($implementation !== null) {
                $metadata = [
                    'source' => 'P07.3',
                    'implementation_id' => $implementation->id,
                    'contract_disposition' => $implementation->contract_disposition->value,
                    'contract_id' => $implementation->contract_id,
                    'contract_version' => $implementation->amendment_contract_version,
                    'baseline_disposition' => $implementation->baseline_disposition->value,
                    'new_baseline_id' => $implementation->new_baseline_id,
                ];
            }

            $locked->transitions()->create([
                'organization_id' => $locked->organization_id,
                'from_state' => $from,
                'to_state' => $target,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'metadata' => $metadata,
                'occurred_at' => $now,
            ]);
            ProjectActivity::record(
                $locked->project,
                $actor,
                'change_request_transitioned',
                'Solicitação de mudança: '.$from->label().' → '.$target->label(),
                'change_request',
                $locked->id,
                ['details' => $locked->code.($reason ? ' · '.$reason : '')],
            );

            return $locked->fresh()->load([
                'requester',
                'analyst',
                'baseline',
                'affectedItems',
                'impactAnalyses.analyst',
                'impactAnalyses.completedBy',
                'implementation.responsible',
                'implementation.completedBy',
                'implementation.contract',
                'implementation.newBaseline',
                'implementation.events.actor',
                'transitions.actor',
            ]);
        });
    }

    public function assignAnalyst(ChangeRequest $changeRequest, int $analystId, User $actor): ChangeRequest
    {
        return DB::transaction(function () use ($changeRequest, $analystId, $actor): ChangeRequest {
            $locked = ChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            if ($locked->state !== ChangeRequestState::Submitted) {
                throw ValidationException::withMessages([
                    'state' => 'O analista só pode ser designado enquanto a solicitação estiver submetida.',
                ]);
            }

            $analyst = $this->resolveRequiredAnalyst($locked->project, $analystId);
            $previousAnalystId = $locked->analyst_id;
            $locked->update([
                'analyst_id' => $analyst->id,
                'updated_by' => $actor->id,
            ]);

            $locked->transitions()->create([
                'organization_id' => $locked->organization_id,
                'from_state' => ChangeRequestState::Submitted,
                'to_state' => ChangeRequestState::Submitted,
                'actor_id' => $actor->id,
                'reason' => 'Responsável pela análise designado: '.$analyst->name.'.',
                'metadata' => [
                    'source' => 'P07.3-R2',
                    'event_type' => 'analyst_assigned',
                    'previous_analyst_id' => $previousAnalystId,
                    'analyst_id' => $analyst->id,
                ],
                'occurred_at' => now(),
            ]);
            ProjectActivity::record(
                $locked->project,
                $actor,
                'change_request_analyst_assigned',
                'Responsável pela análise designado',
                'change_request',
                $locked->id,
                ['details' => $locked->code.' · '.$analyst->name],
            );

            return $locked->fresh()->load(['analyst', 'transitions.actor']);
        });
    }

    /** @param array<string, mixed> $affected */
    private function syncAffectedItems(ChangeRequest $changeRequest, array $affected): void
    {
        $resolved = collect();
        foreach (['requirement', 'task', 'artifact', 'contract', 'document'] as $type) {
            $ids = collect($affected[$type] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
            if ($ids->isEmpty()) {
                continue;
            }

            $models = $this->affectedModels($changeRequest->project, $type, $ids->all());
            if ($models->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    "affected.{$type}" => 'Um dos itens selecionados não pertence ao projeto autorizado.',
                ]);
            }

            foreach ($models as $model) {
                $resolved->push([
                    'organization_id' => $changeRequest->organization_id,
                    'item_type' => $type,
                    'source_id' => $model->id,
                    'code' => $this->affectedCode($type, $model),
                    'title' => $model->title,
                ]);
            }
        }

        $changeRequest->affectedItems()->delete();
        foreach ($resolved as $item) {
            $changeRequest->affectedItems()->create($item);
        }
    }

    /** @param list<int> $ids */
    private function affectedModels(Project $project, string $type, array $ids): EloquentCollection
    {
        return match ($type) {
            'requirement' => $project->requirements()->whereIn('id', $ids)->get(),
            'task' => $project->tasks()->whereIn('id', $ids)->get(),
            'artifact' => $project->artifacts()->whereIn('id', $ids)->get(),
            'contract' => $project->contracts()->whereIn('id', $ids)->get(),
            'document' => $project->documents()->whereIn('id', $ids)->get(),
            default => new EloquentCollection(),
        };
    }

    private function affectedCode(string $type, object $model): ?string
    {
        if (isset($model->code)) {
            return $model->code;
        }

        return $type === 'document'
            ? 'DOC-'.$model->id.'-v'.$model->version
            : null;
    }

    private function resolveRequiredAnalyst(Project $project, int $analystId): User
    {
        $analyst = $this->resolveProjectUser($project, $analystId, 'analyst_id');
        if (! $analyst->hasProjectRole(ProjectRole::ProjectManager, $project)
            && ! $analyst->hasProjectRole(ProjectRole::RequirementsAnalyst, $project)) {
            throw ValidationException::withMessages([
                'analyst_id' => 'Selecione um gerente ou analista de requisitos ativo no projeto.',
            ]);
        }

        return $analyst;
    }

    private function resolveProjectUser(Project $project, int $userId, string $field): User
    {
        $user = User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->whereHas('organizationMemberships', fn ($query) => $query
                ->where('organization_id', $project->organization_id)
                ->where('status', OrganizationMembershipStatus::Active->value))
            ->first();

        if ($user === null || ! $project->hasActiveMember($user)) {
            throw ValidationException::withMessages([
                $field => 'Selecione uma pessoa ativa e vinculada ao projeto.',
            ]);
        }

        return $user;
    }

    private function resolveBaseline(Project $project, ?int $baselineId): ?int
    {
        if ($baselineId === null) {
            return null;
        }

        $exists = $project->baselines()->whereKey($baselineId)->exists();
        if (! $exists) {
            throw ValidationException::withMessages([
                'baseline_id' => 'A baseline de referência não pertence ao projeto autorizado.',
            ]);
        }

        return $baselineId;
    }

    private function validateSubmission(ChangeRequest $changeRequest): void
    {
        Validator::make($changeRequest->only([
            'title',
            'origin',
            'description',
            'justification',
            'urgency',
            'requester_id',
        ]), [
            'title' => ['required', 'string'],
            'origin' => ['required'],
            'description' => ['required', 'string'],
            'justification' => ['required', 'string'],
            'urgency' => ['required'],
            'requester_id' => ['required', 'integer'],
        ], [], [
            'description' => 'descrição',
            'justification' => 'justificativa',
            'urgency' => 'urgência',
        ])->validate();
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
