<?php

namespace App\Enums;

enum ClientType: string
{
    case Client = 'client';
    case Institution = 'institution';
    case Department = 'department';
    case Unit = 'unit';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Cliente',
            self::Institution => 'Órgão ou instituição',
            self::Department => 'Setor',
            self::Unit => 'Unidade demandante',
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
