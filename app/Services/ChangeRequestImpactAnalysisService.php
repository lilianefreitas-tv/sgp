<?php

namespace App\Services;

use App\Enums\ChangeRequestAnalysisStatus;
use App\Enums\ChangeRequestClassification;
use App\Enums\ChangeRequestRecommendation;
use App\Enums\ChangeRequestRiskLevel;
use App\Enums\ChangeRequestState;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImpactAnalysis;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChangeRequestImpactAnalysisService
{
    /** @var list<string> */
    private const NARRATIVE_FIELDS = [
        'executive_summary',
        'scope_impact',
        'requirements_impact',
        'technical_impact',
        'data_impact',
        'security_impact',
        'schedule_impact',
        'resources_impact',
        'cost_impact',
        'contract_impact',
        'quality_impact',
        'testing_impact',
        'operations_impact',
        'documentation_impact',
        'risks_and_mitigations',
    ];

    /** @param array<string, mixed> $data */
    public function saveDraft(ChangeRequest $changeRequest, array $data, User $actor): ChangeRequestImpactAnalysis
    {
        return DB::transaction(function () use ($changeRequest, $data, $actor): ChangeRequestImpactAnalysis {
            [$lockedRequest, $analysis] = $this->lockedDraft($changeRequest, $actor);
            $analysis->fill($this->normalized($data));
            $analysis->updated_by = $actor->id;
            $analysis->save();

            ProjectActivity::record(
                $lockedRequest->project,
                $actor,
                'change_request_analysis_saved',
                'Rascunho da análise de impacto salvo',
                'change_request',
                $lockedRequest->id,
                ['details' => $lockedRequest->code.' · Rodada '.$analysis->round],
            );

            return $analysis->fresh(['analyst', 'completedBy']);
        });
    }

    /** @param array<string, mixed> $data */
    public function complete(ChangeRequest $changeRequest, array $data, User $actor): ChangeRequestImpactAnalysis
    {
        $validated = Validator::make($data, $this->completionRules(), [], $this->attributes())->validate();

        return DB::transaction(function () use ($changeRequest, $validated, $actor): ChangeRequestImpactAnalysis {
            [$lockedRequest, $analysis] = $this->lockedDraft($changeRequest, $actor);
            $analysis->fill($this->normalized($validated));
            $analysis->status = ChangeRequestAnalysisStatus::Completed;
            $analysis->completed_by = $actor->id;
            $analysis->completed_at = now();
            $analysis->updated_by = $actor->id;
            $analysis->save();

            ProjectActivity::record(
                $lockedRequest->project,
                $actor,
                'change_request_analysis_completed',
                'Análise de impacto concluída',
                'change_request',
                $lockedRequest->id,
                [
                    'details' => $lockedRequest->code.' · Rodada '.$analysis->round,
                    'recommendation' => $analysis->recommendation->value,
                    'risk_level' => $analysis->risk_level->value,
                ],
            );

            return $analysis->fresh(['analyst', 'completedBy']);
        });
    }

    /** @return array{0: ChangeRequest, 1: ChangeRequestImpactAnalysis} */
    private function lockedDraft(ChangeRequest $changeRequest, User $actor): array
    {
        $lockedRequest = ChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
        if ($lockedRequest->state !== ChangeRequestState::UnderAnalysis) {
            throw ValidationException::withMessages([
                'analysis' => 'A análise de impacto só pode ser alterada enquanto a solicitação estiver em análise.',
            ]);
        }

        if (! $actor->canManageProject($lockedRequest->project)
            && $lockedRequest->analyst_id !== $actor->id) {
            throw ValidationException::withMessages([
                'analysis' => 'Somente o analista responsável ou a gestão do projeto pode elaborar esta análise.',
            ]);
        }

        $analysis = ChangeRequestImpactAnalysis::query()
            ->where('change_request_id', $lockedRequest->id)
            ->latest('round')
            ->lockForUpdate()
            ->firstOrFail();

        if ($analysis->status !== ChangeRequestAnalysisStatus::Draft) {
            throw ValidationException::withMessages([
                'analysis' => 'A rodada de análise já foi concluída e não pode ser alterada.',
            ]);
        }

        return [$lockedRequest, $analysis];
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalized(array $data): array
    {
        $normalized = collect($data)->only([
            'classification',
            'risk_level',
            'recommendation',
            ...self::NARRATIVE_FIELDS,
            'estimated_effort_hours',
            'estimated_schedule_days',
            'estimated_cost_amount',
        ])->all();

        foreach (self::NARRATIVE_FIELDS as $field) {
            if (array_key_exists($field, $normalized)) {
                $value = trim((string) $normalized[$field]);
                $normalized[$field] = $value === '' ? null : $value;
            }
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function completionRules(): array
    {
        $rules = [
            'classification' => ['required', Rule::enum(ChangeRequestClassification::class)],
            'risk_level' => ['required', Rule::enum(ChangeRequestRiskLevel::class)],
            'recommendation' => ['required', Rule::enum(ChangeRequestRecommendation::class)],
            'estimated_effort_hours' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'estimated_schedule_days' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'estimated_cost_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];

        foreach (self::NARRATIVE_FIELDS as $field) {
            $rules[$field] = ['required', 'string', 'min:3', 'max:10000'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        return [
            'classification' => 'classificação',
            'risk_level' => 'nível de risco',
            'recommendation' => 'recomendação técnica',
            'executive_summary' => 'síntese executiva',
            'scope_impact' => 'impacto em visão e escopo',
            'requirements_impact' => 'impacto em requisitos e regras',
            'technical_impact' => 'impacto técnico e arquitetural',
            'data_impact' => 'impacto em dados e migração',
            'security_impact' => 'impacto em segurança e privacidade',
            'schedule_impact' => 'impacto em prazo e cronograma',
            'resources_impact' => 'impacto em recursos e equipe',
            'cost_impact' => 'impacto em custo e preço',
            'contract_impact' => 'impacto contratual',
            'quality_impact' => 'impacto em qualidade',
            'testing_impact' => 'impacto em testes',
            'operations_impact' => 'impacto em operação',
            'documentation_impact' => 'impacto em documentação',
            'risks_and_mitigations' => 'riscos e mitigações',
        ];
    }
}
