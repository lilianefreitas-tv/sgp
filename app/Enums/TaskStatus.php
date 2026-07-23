<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Backlog = 'backlog';
    case ToDo = 'to_do';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case InTesting = 'in_testing';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::ToDo => 'A Fazer',
            self::InProgress => 'Em Andamento',
            self::InReview => 'Em Revisão',
            self::InTesting => 'Em Teste',
            self::Completed => 'Concluído',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Backlog => 'bg-[#F3F5F6] text-[#667680]',
            self::ToDo => 'bg-[#E6F0F8] text-[#287EA1]',
            self::InProgress => 'bg-[#EAF0FB] text-[#4B67A1]',
            self::InReview => 'bg-[#FFF4DE] text-[#9A6415]',
            self::InTesting => 'bg-[#F2EAFB] text-[#7752A5]',
            self::Completed => 'bg-[#E4F3F0] text-[#2E8B74]',
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
