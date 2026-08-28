<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectBaselineService
{
    public function createFromChange(
        ChangeRequest $changeRequest,
        string $title,
        string $justification,
        User $actor,
    ): ProjectBaseline {
        $project = $changeRequest->project;

        return $this->create($project, [
            'title' => $title,
            'justification' => $justification,
            'source_change_request_id' => $changeRequest->id,
            'requirements' => $project->requirements()->where('is_active', true)->pluck('id')->all(),
            'artifacts' => $project->artifacts()->whereNull('archived_at')->pluck('id')->all(),
            'contracts' => $project->contracts()->pluck('id')->all(),
            'origin_documents' => $project->originDocumentVersions()->where('origin_status', 'current')->pluck('id')->all(),
        ], $actor);
    }

    public function create(Project $project, array $data, User $actor): ProjectBaseline
    {
        return DB::transaction(function () use ($project, $data, $actor): ProjectBaseline {
            DB::table('projects')->where('id', $project->id)->where('organization_id', $project->organization_id)->lockForUpdate()->firstOrFail();
            $version = (int) $project->baselines()->max('version') + 1;
            $baseline = $project->baselines()->create([
                'organization_id' => $project->organization_id,
                'source_change_request_id' => $data['source_change_request_id'] ?? null,
                'version' => $version,
                'title' => $data['title'],
                'justification' => $data['justification'],
                'established_at' => now(),
                'created_by' => $actor->id,
            ]);

            $baseline->items()->create([
                'organization_id' => $project->organization_id,
                'item_type' => 'project', 'source_id' => $project->id,
                'source_version' => null, 'code' => $project->code, 'title' => $project->name,
                'snapshot' => $project->only(['code', 'name', 'description', 'objective', 'justification', 'scope_included', 'scope_excluded', 'assumptions', 'constraints', 'success_criteria', 'status', 'start_date', 'expected_end_date']),
            ]);

            $this->capture($baseline, $project->requirements()->whereIn('id', $data['requirements'] ?? [])->get(), 'requirement',
                fn ($item) => [$item->current_version, $item->code, $item->title, $item->only(['code', 'title', 'description', 'type', 'priority', 'status', 'acceptance_criteria', 'source', 'current_version'])]);
            $this->capture($baseline, $project->artifacts()->whereIn('id', $data['artifacts'] ?? [])->get(), 'artifact',
                fn ($item) => [$item->current_revision_sequence, $item->code, $item->title, $item->only(['code', 'type', 'title', 'description', 'current_revision_sequence', 'workflow_state'])]);
            $this->capture($baseline, $project->contracts()->whereIn('id', $data['contracts'] ?? [])->get(), 'contract',
                fn ($item) => [$item->versions()->max('version'), $item->code, $item->title, $item->only(['code', 'title', 'contract_kind', 'status', 'object', 'external_reference', 'start_date', 'end_date', 'amount', 'capacity_notes'])]);
            $this->capture($baseline, $project->originDocumentVersions()->whereIn('id', $data['origin_documents'] ?? [])->get(), 'origin_document',
                fn ($item) => [$item->origin_version, null, $item->origin_title ?: $item->original_name, $item->only(['origin_title', 'origin_category', 'origin_version', 'external_reference', 'original_document_date', 'original_name', 'sha256'])]);

            return $baseline->load(['items', 'creator']);
        });
    }

    private function capture(ProjectBaseline $baseline, iterable $items, string $type, callable $present): void
    {
        foreach ($items as $item) {
            [$version, $code, $title, $snapshot] = $present($item);
            $baseline->items()->create(['organization_id' => $baseline->organization_id, 'item_type' => $type,
                'source_id' => $item->id, 'source_version' => $version ? (string) $version : null,
                'code' => $code, 'title' => $title, 'snapshot' => $snapshot]);
        }
    }
}
