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
            self::Owner => 'Administrador principal',
            self::Administrator => 'Administrador da organização',
            self::Member => 'Usuário da organização',
            self::Reader => 'Acesso de consulta',
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
