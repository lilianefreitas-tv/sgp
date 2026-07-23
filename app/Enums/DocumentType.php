<?php

namespace App\Enums;

enum DocumentType: string
{
    case Vision = 'vision';
    case RequirementsList = 'requirements_list';
    case TasksList = 'tasks_list';
    case ConsolidatedBacklog = 'consolidated_backlog';

    public function label(): string
    {
        return match ($this) {
            self::Vision => 'Documento de Visão',
            self::RequirementsList => 'Lista de Requisitos',
            self::TasksList => 'Lista de Tarefas',
            self::ConsolidatedBacklog => 'Backlog Consolidado do Projeto',
        };
    }

    public function shortCode(): string
    {
        return match ($this) {
            self::Vision => 'VIS',
            self::RequirementsList => 'REQ',
            self::TasksList => 'TAR',
            self::ConsolidatedBacklog => 'BKL',
        };
    }

    public function slug(): string
    {
        return match ($this) {
            self::Vision => 'documento-de-visao',
            self::RequirementsList => 'lista-de-requisitos',
            self::TasksList => 'lista-de-tarefas',
            self::ConsolidatedBacklog => 'backlog-consolidado',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
