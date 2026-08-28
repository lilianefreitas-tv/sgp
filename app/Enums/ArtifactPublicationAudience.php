<?php

namespace App\Enums;

enum ArtifactPublicationAudience: string
{
    case Internal = 'internal';
    case Client = 'client';
    case Audit = 'audit';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Uso interno',
            self::Client => 'Cliente',
            self::Audit => 'Auditoria',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $audience) => [$audience->value => $audience->label()])->all();
    }
}
