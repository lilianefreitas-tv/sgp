<?php

namespace App\Enums;

enum TestCaseStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Ready => 'Pronto para execução',
            self::Retired => 'Descontinuado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-[#F3F5F6] text-[#667680]',
            self::Ready => 'bg-[#E6F0F8] text-[#287EA1]',
            self::Retired => 'bg-[#FBE8E8] text-[#A23838]',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $item) => [$item->value => $item->label()])->all();
    }
}
