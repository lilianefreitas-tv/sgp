<?php

namespace App\Enums;

enum HomologationStatus: string
{
    case Approved = 'approved';
    case ApprovedWithReservations = 'approved_with_reservations';
    case Rejected = 'rejected';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Aprovada',
            self::ApprovedWithReservations => 'Aprovada com ressalvas',
            self::Rejected => 'Reprovada',
            self::Blocked => 'Bloqueada',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Approved => 'bg-[#E4F3F0] text-[#256C5C]',
            self::ApprovedWithReservations => 'bg-[#FFF4DE] text-[#9A6415]',
            self::Rejected => 'bg-[#FBE8E8] text-[#A23838]',
            self::Blocked => 'bg-[#F2EAFB] text-[#594173]',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $item) => [$item->value => $item->label()])->all();
    }
}
