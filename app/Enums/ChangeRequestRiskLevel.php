<?php

namespace App\Enums;

enum ChangeRequestRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baixo',
            self::Medium => 'Médio',
            self::High => 'Alto',
            self::Critical => 'Crítico',
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
