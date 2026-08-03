<?php

namespace App\Enums;

enum ExecutionNature: string
{
    case Internal = 'internal';
    case Contracted = 'contracted';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interno',
            self::Contracted => 'Contratado para cliente',
            self::Mixed => 'Misto',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Internal => 'Executado para atender uma necessidade da própria organização.',
            self::Contracted => 'Executado para um cliente ou demandante externo.',
            self::Mixed => 'Combina finalidade interna e entrega para cliente ou demandante.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $nature) => [$nature->value => $nature->label()])
            ->all();
    }
}
