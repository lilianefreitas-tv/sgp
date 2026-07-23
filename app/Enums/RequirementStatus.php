<?php

namespace App\Enums;

enum RequirementStatus: string
{
    case Proposed = 'proposed';
    case UnderAnalysis = 'under_analysis';
    case Approved = 'approved';
    case InDevelopment = 'in_development';
    case InTesting = 'in_testing';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposto',
            self::UnderAnalysis => 'Em análise',
            self::Approved => 'Aprovado',
            self::InDevelopment => 'Em desenvolvimento',
            self::InTesting => 'Em teste',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Proposed => 'bg-[#F3F5F6] text-[#667680]',
            self::UnderAnalysis => 'bg-[#FFF4DE] text-[#9A6415]',
            self::Approved => 'bg-[#E6F0F8] text-[#287EA1]',
            self::InDevelopment => 'bg-[#EAF0FB] text-[#4B67A1]',
            self::InTesting => 'bg-[#F2EAFB] text-[#7752A5]',
            self::Delivered => 'bg-[#E4F3F0] text-[#2E8B74]',
            self::Cancelled => 'bg-[#FBE8E8] text-[#C44B4B]',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
