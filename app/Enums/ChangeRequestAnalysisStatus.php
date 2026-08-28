<?php

namespace App\Enums;

enum ChangeRequestAnalysisStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Em elaboração',
            self::Completed => 'Concluída',
        };
    }
}
