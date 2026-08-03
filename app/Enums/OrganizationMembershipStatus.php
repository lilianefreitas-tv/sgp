<?php

namespace App\Enums;

enum OrganizationMembershipStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Invited => 'Convidado',
            self::Suspended => 'Suspenso',
        };
    }
}
