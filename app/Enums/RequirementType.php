<?php

namespace App\Enums;

enum RequirementType: string
{
    case Functional = 'functional';
    case NonFunctional = 'non_functional';
    case BusinessRule = 'business_rule';
    case Technical = 'technical';
    case Interface = 'interface';
    case Data = 'data';

    public function label(): string
    {
        return match ($this) {
            self::Functional => 'Funcional',
            self::NonFunctional => 'Não funcional',
            self::BusinessRule => 'Regra de negócio',
            self::Technical => 'Técnico',
            self::Interface => 'Interface',
            self::Data => 'Dados',
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
