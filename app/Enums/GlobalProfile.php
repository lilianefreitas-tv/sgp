<?php

namespace App\Enums;

enum GlobalProfile: string
{
    case Administrator = 'administrator';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::User => 'Usuário',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $profile) => [$profile->value => $profile->label()])
            ->all();
    }
}
