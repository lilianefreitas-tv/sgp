<?php

namespace App\Enums;

enum ArtifactPublicationMode: string
{
    case Consolidated = 'consolidated';
    case Incremental = 'incremental';
    case Comparative = 'comparative';
    case Individual = 'individual';
    case Specialized = 'specialized';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Consolidated => 'Consolidado vigente',
            self::Incremental => 'Alterações desde uma revisão',
            self::Comparative => 'Comparativo entre revisões',
            self::Individual => 'Ficha individual',
            self::Specialized => 'Documento especializado',
            self::Custom => 'Pacote personalizado',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $mode) => [$mode->value => $mode->label()])->all();
    }
}
