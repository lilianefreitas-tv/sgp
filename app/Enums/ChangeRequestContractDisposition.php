<?php

namespace App\Enums;

enum ChangeRequestContractDisposition: string
{
    case NotApplicable = 'not_applicable';
    case NoAmendment = 'no_amendment';
    case AmendmentRequired = 'amendment_required';
    case AmendmentRegistered = 'amendment_registered';

    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'Não aplicável',
            self::NoAmendment => 'Sem necessidade de aditivo',
            self::AmendmentRequired => 'Aditivo necessário',
            self::AmendmentRegistered => 'Aditivo formalizado',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $item) => [$item->value => $item->label()])
            ->all();
    }
}
