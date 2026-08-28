<?php

namespace App\Services;

use App\Enums\ArtifactType;
use App\Models\Artifact;
use App\Models\Initiative;
use App\Models\User;
use Illuminate\Support\Arr;

class InitiativeDocumentService
{
    public function __construct(
        private readonly ArtifactRevisionService $revisions,
        private readonly ArtifactSnapshotCanonicalizer $canonicalizer,
    ) {}

    public function synchronizeDossier(Initiative $initiative, User $actor): Artifact
    {
        $initiative->load([
            'configurationVersions' => fn ($query) => $query->orderByDesc('sequence'),
            'opportunity.assessments' => fn ($query) => $query->orderBy('created_at'),
            'opportunity.proposals.versions' => fn ($query) => $query->orderBy('sequence'),
            'opportunity.negotiations' => fn ($query) => $query->orderBy('occurred_at'),
            'project',
        ]);

        $snapshot = $this->canonicalizer->canonicalize($this->snapshot($initiative));
        $metadata = [
            'document_kind' => 'initiative_dossier',
            'generated_from' => ['initiative', 'commercial_journey'],
            'generated_at' => now()->toIso8601String(),
        ];

        $artifact = Artifact::query()
            ->where('organization_id', $initiative->organization_id)
            ->where('initiative_id', $initiative->id)
            ->where('type', ArtifactType::InitiativeRecord->value)
            ->where('title', 'Dossiê da iniciativa')
            ->whereNull('archived_at')
            ->oldest('id')
            ->first();

        if ($artifact === null) {
            return $this->revisions->create([
                'initiative_id' => $initiative->id,
                'type' => ArtifactType::InitiativeRecord,
                'title' => 'Dossiê da iniciativa',
                'description' => 'Consolidação automática da iniciativa e de sua evolução comercial.',
                'content' => $snapshot,
                'metadata' => $metadata,
                'schema_version' => 1,
                'change_reason' => 'Dossiê gerado a partir dos registros operacionais.',
            ], $actor);
        }

        $current = $artifact->revisions()->where('sequence', $artifact->current_revision_sequence)->first();
        if ($current !== null && $current->content === $snapshot) {
            return $artifact->fresh(['revisions']) ?? $artifact;
        }

        $this->revisions->revise(
            $artifact,
            $snapshot,
            $metadata,
            1,
            'Dossiê atualizado a partir dos registros operacionais.',
            $actor,
            true,
        );

        return $artifact->fresh(['revisions']) ?? $artifact;
    }

    /** @return array<string, mixed> */
    public function snapshot(Initiative $initiative): array
    {
        $opportunity = $initiative->opportunity;
        $configuration = $initiative->configurationVersions->firstWhere('superseded_at', null)
            ?? $initiative->configurationVersions->first();

        return [
            'identificacao' => [
                'codigo' => $initiative->code,
                'titulo' => $initiative->title,
                'contexto' => $initiative->context,
                'origem' => $initiative->origin->value,
                'estado' => $initiative->state->value,
            ],
            'configuracao' => $configuration === null ? null : Arr::only($configuration->toArray(), [
                'sequence', 'execution_nature', 'financial_management_mode', 'management_level', 'methodology', 'justification',
            ]),
            'jornada_comercial' => $opportunity === null ? null : [
                'oportunidade' => Arr::only($opportunity->toArray(), [
                    'code', 'title', 'summary', 'state', 'priority', 'estimated_value', 'expected_decision_at', 'loss_reason',
                ]),
                'levantamentos' => $opportunity->assessments->map(fn ($item) => Arr::only($item->toArray(), [
                    'state', 'needs', 'constraints', 'assumptions', 'objectives', 'participants', 'observations', 'created_at',
                ]))->values()->all(),
                'propostas' => $opportunity->proposals->map(fn ($proposal) => [
                    'codigo' => $proposal->code,
                    'estado' => $proposal->state,
                    'versoes' => $proposal->versions->map(fn ($version) => Arr::only($version->toArray(), [
                        'sequence', 'scope_summary', 'solution_summary', 'assumptions', 'exclusions', 'payment_terms',
                        'estimated_start', 'estimated_duration', 'estimated_value', 'pricing_model', 'validity_until', 'change_reason',
                    ]))->values()->all(),
                ])->values()->all(),
                'negociacoes' => $opportunity->negotiations->map(fn ($item) => Arr::only($item->toArray(), [
                    'interaction_type', 'occurred_at', 'summary', 'counterproposal', 'decision', 'next_step',
                    'proposal_id', 'proposal_version_id',
                ]))->values()->all(),
            ],
            'conversao' => [
                'convertida_em_projeto' => $initiative->project !== null,
                'projeto' => $initiative->project === null ? null : [
                    'codigo' => $initiative->project->code,
                    'nome' => $initiative->project->name,
                ],
            ],
        ];
    }
}
