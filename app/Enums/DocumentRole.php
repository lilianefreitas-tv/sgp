<?php

namespace App\Enums;

enum DocumentRole: string
{
    case Author = 'author';
    case Reviewer = 'reviewer';
    case Approver = 'approver';

    public function label(): string
    {
        return match ($this) {
            self::Author => 'Autor',
            self::Reviewer => 'Revisor',
            self::Approver => 'Aprovador',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $role) => [$role->value => $role->label()])->all();
    }
}
