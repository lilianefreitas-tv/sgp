<?php

namespace App\Enums;

enum ContractEntryMode: string
{
    case Attachment = 'attachment';
    case Editor = 'editor';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Attachment => 'Anexar contrato existente',
            self::Editor => 'Redigir ou colar no SGP',
            self::Hybrid => 'Texto e documentos anexos',
        };
    }
}
