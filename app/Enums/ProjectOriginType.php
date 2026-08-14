<?php

namespace App\Enums;

enum ProjectOriginType: string
{
    case Initiative = 'initiative';
    case Direct = 'direct';
    case Incorporated = 'incorporated';

    public function label(): string
    {
        return match ($this) {
            self::Initiative => 'Originado de iniciativa',
            self::Direct => 'Projeto interno ou direto',
            self::Incorporated => 'Projeto incorporado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Initiative => 'Criado a partir de uma iniciativa acompanhada no SGP.',
            self::Direct => 'Iniciado diretamente no SGP, sem reconstrução de jornada comercial.',
            self::Incorporated => 'Já existia antes do cadastro e possui documentação produzida externamente.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
