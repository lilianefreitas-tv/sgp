<?php

namespace App\Enums;

enum OrganizationType: string
{
    case Company = 'company';
    case PublicBody = 'public_body';
    case Team = 'team';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Empresa',
            self::PublicBody => 'Órgão público',
            self::Team => 'Equipe',
            self::Other => 'Outro',
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
