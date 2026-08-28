<?php

namespace App\Enums;

enum ChangeRequestState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderAnalysis = 'under_analysis';
    case Returned = 'returned';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Implemented = 'implemented';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Submitted => 'Submetida',
            self::UnderAnalysis => 'Em análise',
            self::Returned => 'Devolvida',
            self::Approved => 'Aprovada',
            self::Rejected => 'Rejeitada',
            self::Implemented => 'Implementada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Implemented, self::Cancelled], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $state) => [$state->value => $state->label()])
            ->all();
    }
}
