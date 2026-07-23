<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectAttachment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectHistoryService
{
    /** @return array<string, string> */
    public static function filters(): array
    {
        return [
            '' => 'Todos os eventos',
            'project' => 'Projeto e equipe',
            'requirement' => 'Requisitos',
            'task' => 'Tarefas e Kanban',
            'document' => 'Documentos',
            'comment' => 'Comentários',
            'attachment' => 'Anexos',
        ];
    }

    public function paginate(Project $project, string $filter = '', int $perPage = 20): LengthAwarePaginator
    {
        $items = collect()
            ->merge($this->projectEvents($project))
            ->merge($this->requirementEvents($project))
            ->merge($this->taskEvents($project))
            ->merge($this->documentEvents($project))
            ->merge($this->commentEvents($project))
            ->merge($this->attachmentEvents($project))
            ->when($filter !== '', fn (Collection $events) => $events
                ->where('category', $filter))
            ->sortByDesc(fn (array $event) => $event['occurred_at']->getTimestamp())
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    private function projectEvents(Project $project): Collection
    {
        $activities = $project->activities()
            ->with('user')
            ->get();
        $events = collect();

        if (! $activities->contains('event_type', 'project_created')) {
            $events->push([
                'category' => 'project',
                'title' => 'Projeto cadastrado',
                'description' => $project->code.' · '.$project->name,
                'actor' => $project->manager?->name ?? 'Sistema',
                'occurred_at' => $project->created_at,
                'tone' => 'blue',
            ]);
        }

        return $events->merge($activities->map(fn ($activity) => [
                'category' => match ($activity->subject_type) {
                    'requirement' => 'requirement',
                    'task' => 'task',
                    'document' => 'document',
                    default => 'project',
                },
                'title' => $activity->description,
                'description' => $activity->metadata['details'] ?? null,
                'actor' => $activity->user?->name ?? 'Sistema',
                'occurred_at' => $activity->created_at,
                'tone' => match ($activity->subject_type) {
                    'requirement' => 'purple',
                    'task' => 'green',
                    'document' => 'amber',
                    default => 'blue',
                },
            ]));
    }

    private function requirementEvents(Project $project): Collection
    {
        $recordedCreations = $project->activities()
            ->where('event_type', 'requirement_created')
            ->where('subject_type', 'requirement')
            ->pluck('subject_id');

        return $project->requirements()
            ->with(['versions.changedBy'])
            ->get()
            ->flatMap(function ($requirement) use ($recordedCreations) {
                $created = [];
                if (! $recordedCreations->contains($requirement->id)) {
                    $created[] = [
                        'category' => 'requirement',
                        'title' => 'Requisito cadastrado',
                        'description' => $requirement->code.' · '.$requirement->title,
                        'actor' => $requirement->responsible?->name ?? 'Equipe do projeto',
                        'occurred_at' => $requirement->created_at,
                        'tone' => 'purple',
                    ];
                }

                return collect($created)->merge($requirement->versions->map(fn ($version) => [
                    'category' => 'requirement',
                    'title' => 'Requisito alterado para a versão '.($version->version_number + 1),
                    'description' => $requirement->code.' · '.($version->change_reason ?: 'Alteração sem justificativa informada.'),
                    'actor' => $version->changedBy->name,
                    'occurred_at' => $version->created_at,
                    'tone' => 'purple',
                ]));
            });
    }

    private function taskEvents(Project $project): Collection
    {
        return $project->tasks()
            ->with(['histories.changedBy'])
            ->get()
            ->flatMap(function ($task) {
                if ($task->histories->isEmpty()) {
                    return [[
                        'category' => 'task',
                        'title' => 'Tarefa cadastrada',
                        'description' => $task->code.' · '.$task->title,
                        'actor' => $task->responsible?->name ?? 'Equipe do projeto',
                        'occurred_at' => $task->created_at,
                        'tone' => 'green',
                    ]];
                }

                return $task->histories->map(function ($history) use ($task) {
                    $description = match ($history->event) {
                        'created' => 'Tarefa cadastrada',
                        'status_changed', 'kanban_moved' => sprintf(
                            'Status: %s → %s',
                            TaskStatus::tryFrom($history->from_status)?->label() ?? 'Não definido',
                            TaskStatus::tryFrom($history->to_status)?->label() ?? 'Não definido',
                        ),
                        'deactivated' => 'Tarefa inativada',
                        'reactivated' => 'Tarefa reativada',
                        default => 'Tarefa atualizada',
                    };

                    return [
                        'category' => 'task',
                        'title' => $description,
                        'description' => $task->code.' · '.$task->title.($history->notes ? ' · '.$history->notes : ''),
                        'actor' => $history->changedBy->name,
                        'occurred_at' => $history->created_at,
                        'tone' => 'green',
                    ];
                });
            });
    }

    private function documentEvents(Project $project): Collection
    {
        return $project->documents()
            ->with('generator')
            ->get()
            ->map(fn ($document) => [
                'category' => 'document',
                'title' => 'Documento gerado',
                'description' => $document->title.' · v'.$document->version,
                'actor' => $document->generator->name,
                'occurred_at' => $document->generated_at,
                'tone' => 'amber',
            ]);
    }

    private function commentEvents(Project $project): Collection
    {
        return $project->comments()
            ->with('author')
            ->get()
            ->map(fn ($comment) => [
                'category' => 'comment',
                'title' => 'Comentário registrado',
                'description' => str($comment->body)->squish()->limit(180)->toString(),
                'actor' => $comment->author->name,
                'occurred_at' => $comment->created_at,
                'tone' => 'cyan',
            ]);
    }

    private function attachmentEvents(Project $project): Collection
    {
        return $project->attachments()
            ->withTrashed()
            ->with(['uploader', 'deletedBy'])
            ->get()
            ->flatMap(function (ProjectAttachment $attachment) {
                $events = [[
                    'category' => 'attachment',
                    'title' => 'Arquivo anexado',
                    'description' => $attachment->original_name,
                    'actor' => $attachment->uploader->name,
                    'occurred_at' => $attachment->created_at,
                    'tone' => 'slate',
                ]];

                if ($attachment->deleted_at) {
                    $events[] = [
                        'category' => 'attachment',
                        'title' => 'Anexo removido da consulta',
                        'description' => $attachment->original_name,
                        'actor' => $attachment->deletedBy?->name ?? 'Sistema',
                        'occurred_at' => $attachment->deleted_at,
                        'tone' => 'red',
                    ];
                }

                return $events;
            });
    }
}
