<?php

namespace App\Enums;

enum InitiativeOrigin: string
{
    case Commercial = 'commercial';
    case ExistingContract = 'existing_contract';
    case Internal = 'internal';
    case Direct = 'direct';

    public function label(): string
    {
        return match ($this) {
            self::Commercial => 'Comercial',
            self::ExistingContract => 'Contratação existente',
            self::Internal => 'Interna',
            self::Direct => 'Direta',
        };
    }
}
