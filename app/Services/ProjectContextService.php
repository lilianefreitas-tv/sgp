<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

class ProjectContextService
{
    /** @return array{type: string, id: int, label: string, model: Model} */
    public function resolve(Project $project, string $context): array
    {
        abort_unless(str_contains($context, ':'), 422, 'O contexto informado é inválido.');

        [$type, $id] = explode(':', $context, 2);
        abort_unless(ctype_digit($id), 422, 'O contexto informado é inválido.');

        $model = match ($type) {
            'project' => $project->newQuery()
                ->whereKey((int) $id)
                ->whereKey($project->id)
                ->firstOrFail(),
            'requirement' => $project->requirements()->whereKey((int) $id)->firstOrFail(),
            'task' => $project->tasks()->whereKey((int) $id)->firstOrFail(),
            default => abort(422, 'O contexto informado é inválido.'),
        };

        return [
            'type' => $type,
            'id' => (int) $model->getKey(),
            'label' => match ($type) {
                'project' => $project->code.' · Projeto',
                'requirement' => $model->code.' · '.$model->title,
                'task' => $model->code.' · '.$model->title,
            },
            'model' => $model,
        ];
    }

    /** @return array<int, array{value: string, label: string, group: string}> */
    public function options(Project $project): array
    {
        $options = [[
            'value' => 'project:'.$project->id,
            'label' => $project->code.' · '.$project->name,
            'group' => 'Projeto',
        ]];

        foreach ($project->requirements()->orderBy('code')->get(['id', 'code', 'title']) as $requirement) {
            $options[] = [
                'value' => 'requirement:'.$requirement->id,
                'label' => $requirement->code.' · '.$requirement->title,
                'group' => 'Requisitos',
            ];
        }

        foreach ($project->tasks()->orderBy('code')->get(['id', 'code', 'title']) as $task) {
            $options[] = [
                'value' => 'task:'.$task->id,
                'label' => $task->code.' · '.$task->title,
                'group' => 'Tarefas',
            ];
        }

        return $options;
    }

    /** @param iterable<int, object> $records */
    public function addLabels(Project $project, iterable $records): void
    {
        $labels = collect($this->options($project))->keyBy('value');

        foreach ($records as $record) {
            $key = $record->context_type.':'.$record->context_id;
            $record->context_label = $labels->get($key)['label'] ?? 'Registro indisponível';
        }
    }
}
