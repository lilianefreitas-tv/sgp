<?php

namespace App\Enums;

enum TestCaseSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Crítica',
            self::High => 'Alta',
            self::Medium => 'Média',
            self::Low => 'Baixa',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Critical => 'bg-[#FBE8E8] text-[#A23838]',
            self::High => 'bg-[#FFF0E3] text-[#A85520]',
            self::Medium => 'bg-[#FFF4DE] text-[#9A6415]',
            self::Low => 'bg-[#E6F0F8] text-[#287EA1]',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $item) => [$item->value => $item->label()])->all();
    }
}
