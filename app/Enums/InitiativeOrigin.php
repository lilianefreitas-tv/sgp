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

    public function description(): string
    {
        return match ($this) {
            self::Commercial => 'Demanda que percorre oportunidade, proposta, negociação e aceite antes da conversão.',
            self::ExistingContract => 'Demanda amparada por instrumento já registrado; exige contrato vinculado antes da conversão.',
            self::Internal => 'Necessidade da própria organização, sem jornada comercial obrigatória.',
            self::Direct => 'Demanda operacional autorizada para conversão direta, sem etapa comercial.',
        };
    }
}
