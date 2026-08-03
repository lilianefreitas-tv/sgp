<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Member = 'member';
    case Reader = 'reader';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário',
            self::Administrator => 'Administrador',
            self::Member => 'Membro',
            self::Reader => 'Leitor',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
