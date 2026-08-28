<?php

namespace App\Enums;

enum ChangeRequestBaselineDisposition: string
{
    case CreateNew = 'create_new';
    case NotRequired = 'not_required';

    public function label(): string
    {
        return match ($this) {
            self::CreateNew => 'Constituir nova baseline',
            self::NotRequired => 'Nova baseline não necessária',
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
