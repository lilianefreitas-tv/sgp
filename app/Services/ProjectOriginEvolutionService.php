<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAttachment;
use Illuminate\Support\Collection;

class ProjectOriginEvolutionService
{
    /** @return array{counts: array<string, int>, entries: Collection<int, array<string, mixed>>} */
    public function summarize(Project $project): array
    {
        $baseline = $project->originBaseline()->with('documents')->first();

        if ($baseline === null) {
            return ['counts' => ['updated' => 0, 'added' => 0, 'unchanged' => 0], 'entries' => collect()];
        }

        $current = $project->originDocumentVersions()
            ->where('origin_status', 'current')
            ->get()
            ->keyBy('origin_series_uuid');
        $baselineBySeries = $baseline->documents->keyBy('origin_series_uuid');
        $entries = collect();

        foreach ($baselineBySeries as $series => $reference) {
            $latest = $current->get($series);
            $status = $latest !== null && hash_equals((string) $reference->sha256, (string) $latest->sha256)
                ? 'unchanged'
                : 'updated';

            $entries->push($this->entry($status, $reference, $latest));
        }

        foreach ($current->filter(fn (ProjectAttachment $latest, string $series) => ! $baselineBySeries->has($series)) as $latest) {
            $entries->push($this->entry('added', null, $latest));
        }

        return [
            'counts' => [
                'updated' => $entries->where('status', 'updated')->count(),
                'added' => $entries->where('status', 'added')->count(),
                'unchanged' => $entries->where('status', 'unchanged')->count(),
            ],
            'entries' => $entries->sortBy(fn (array $entry) => [$entry['status'], $entry['title']])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function entry(string $status, ?ProjectAttachment $reference, ?ProjectAttachment $latest): array
    {
        $document = $latest ?? $reference;

        return [
            'status' => $status,
            'title' => $document?->origin_title,
            'category' => $document?->origin_category,
            'reference' => $reference,
            'latest' => $latest,
        ];
    }
}
