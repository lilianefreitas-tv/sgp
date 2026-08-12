<?php

namespace App\Enums;

enum InitiativeState: string
{
    case Draft = 'draft';
    case Qualified = 'qualified';
    case UnderEvaluation = 'under_evaluation';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Qualified => 'Qualificada',
            self::UnderEvaluation => 'Em avaliação',
            self::Converted => 'Convertida',
            self::Cancelled => 'Cancelada',
            self::Archived => 'Arquivada',
        };
    }
}
