<?php

namespace App\Enums;

enum ChangeRequestOrigin: string
{
    case Requester = 'requester';
    case InternalTeam = 'internal_team';
    case Defect = 'defect';
    case TechnicalNeed = 'technical_need';
    case LegalRegulatory = 'legal_regulatory';
    case Audit = 'audit';
    case Improvement = 'improvement';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Requester => 'Cliente ou demandante',
            self::InternalTeam => 'Equipe interna',
            self::Defect => 'Defeito identificado',
            self::TechnicalNeed => 'Necessidade técnica',
            self::LegalRegulatory => 'Exigência legal ou normativa',
            self::Audit => 'Auditoria',
            self::Improvement => 'Melhoria',
            self::Other => 'Outra',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $origin) => [$origin->value => $origin->label()])
            ->all();
    }
}
