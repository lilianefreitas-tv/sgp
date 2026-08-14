<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Em preparação', self::Active => 'Vigente', self::Completed => 'Concluído',
            self::Terminated => 'Encerrado', self::Cancelled => 'Cancelado',
        };
    }
}
