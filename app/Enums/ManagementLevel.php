<?php

namespace App\Enums;

enum ManagementLevel: string
{
    case Simplified = 'simplified';
    case Intermediate = 'intermediate';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Simplified => 'Simplificado',
            self::Intermediate => 'Intermediário',
            self::Complete => 'Completo',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $level) => [$level->value => $level->label()])
            ->all();
    }
}
