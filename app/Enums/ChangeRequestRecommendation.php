<?php

namespace App\Enums;

enum ChangeRequestRecommendation: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case ReturnForAdjustment = 'return_for_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Approve => 'Recomendar aprovação',
            self::Reject => 'Recomendar rejeição',
            self::ReturnForAdjustment => 'Recomendar devolução para ajustes',
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
