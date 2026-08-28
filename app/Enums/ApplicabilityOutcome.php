<?php

namespace App\Enums;

enum ApplicabilityOutcome: string
{
    case Required = 'required';
    case Optional = 'optional';
    case NotApplicable = 'not_applicable';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'Obrigatório', self::Optional => 'Opcional',
            self::NotApplicable => 'Não aplicável', self::Unavailable => 'Indisponível no estado atual',
        };
    }
}
