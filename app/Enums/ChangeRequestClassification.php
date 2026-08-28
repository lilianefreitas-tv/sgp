<?php

namespace App\Enums;

enum ChangeRequestClassification: string
{
    case Correction = 'correction';
    case ScopeChange = 'scope_change';
    case PendingItem = 'pending_item';
    case FutureEvolution = 'future_evolution';

    public function label(): string
    {
        return match ($this) {
            self::Correction => 'Correção',
            self::ScopeChange => 'Mudança de escopo',
            self::PendingItem => 'Pendência',
            self::FutureEvolution => 'Evolução futura',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $item) => [$item->value => $item->label()])
            ->all();
    }
}
