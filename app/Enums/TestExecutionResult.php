<?php

namespace App\Enums;

enum TestExecutionResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Aprovado',
            self::Failed => 'Reprovado',
            self::Blocked => 'Bloqueado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Passed => 'bg-[#E4F3F0] text-[#256C5C]',
            self::Failed => 'bg-[#FBE8E8] text-[#A23838]',
            self::Blocked => 'bg-[#FFF4DE] text-[#9A6415]',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $item) => [$item->value => $item->label()])->all();
    }
}
