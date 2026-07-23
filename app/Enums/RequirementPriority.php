<?php

namespace App\Enums;

enum RequirementPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baixa',
            self::Medium => 'Média',
            self::High => 'Alta',
            self::Critical => 'Crítica',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-[#E4F3F0] text-[#2E8B74]',
            self::Medium => 'bg-[#E6F0F8] text-[#287EA1]',
            self::High => 'bg-[#FFF4DE] text-[#9A6415]',
            self::Critical => 'bg-[#FBE8E8] text-[#C44B4B]',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $priority) => [$priority->value => $priority->label()])
            ->all();
    }
}
