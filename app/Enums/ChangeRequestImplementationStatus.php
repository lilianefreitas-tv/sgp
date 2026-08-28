<?php

namespace App\Enums;

enum ChangeRequestImplementationStatus: string
{
    case Planning = 'planning';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Em planejamento',
            self::InProgress => 'Em execução',
            self::Completed => 'Concluída',
        };
    }
}
