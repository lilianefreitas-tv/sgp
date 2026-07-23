<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Planning = 'planning';
    case InProgress = 'in_progress';
    case InValidation = 'in_validation';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planejamento',
            self::InProgress => 'Em andamento',
            self::InValidation => 'Em validação',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Planning => 'bg-[#FFF4DE] text-[#9A6415]',
            self::InProgress => 'bg-[#E6F0F8] text-[#287EA1]',
            self::InValidation => 'bg-[#F2EAFB] text-[#7752A5]',
            self::Completed => 'bg-[#E4F3F0] text-[#2E8B74]',
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
