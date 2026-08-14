<?php

namespace App\Services;

use App\Models\ProjectBaseline;

class ProjectBaselineComparisonService
{
    public function compare(ProjectBaseline $from, ProjectBaseline $to): array
    {
        $left = $from->items->keyBy(fn ($item) => $item->item_type.':'.$item->source_id);
        $right = $to->items->keyBy(fn ($item) => $item->item_type.':'.$item->source_id);
        $rows = collect($left->keys())->merge($right->keys())->unique()->map(function (string $key) use ($left, $right): array {
            $before = $left->get($key); $after = $right->get($key);
            $status = ! $before ? 'added' : (! $after ? 'removed' : ($this->canonical($before->snapshot) === $this->canonical($after->snapshot) ? 'unchanged' : 'changed'));
            return ['key' => $key, 'type' => ($after ?? $before)->item_type, 'code' => ($after ?? $before)->code,
                'title' => ($after ?? $before)->title, 'status' => $status, 'before' => $before, 'after' => $after,
                'fields' => $status === 'changed' ? $this->fields($before->snapshot, $after->snapshot) : []];
        })->sortBy(fn ($row) => $row['type'].'|'.$row['title'])->values();

        return ['rows' => $rows, 'summary' => $rows->countBy('status')];
    }

    private function fields(array $before, array $after): array
    {
        return collect(array_unique(array_merge(array_keys($before), array_keys($after))))->filter(fn ($field) => $this->canonical($before[$field] ?? null) !== $this->canonical($after[$field] ?? null))
            ->map(fn ($field) => ['field' => $field, 'before' => $before[$field] ?? null, 'after' => $after[$field] ?? null])->values()->all();
    }

    private function canonical(mixed $value): string
    {
        if (is_array($value)) { ksort($value); }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
