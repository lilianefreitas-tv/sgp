<?php

namespace App\Enums;

enum ArtifactType: string
{
    case InitiativeRecord = 'initiative_record';
    case ProjectRecord = 'project_record';
    case StructuredRecord = 'structured_record';

    public function label(): string
    {
        return match ($this) {
            self::InitiativeRecord => 'Registro da iniciativa',
            self::ProjectRecord => 'Registro do projeto',
            self::StructuredRecord => 'Registro estruturado',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }

    /** @return array<string, string> */
    public static function optionsForParent(string $parentType): array
    {
        return collect(self::cases())
            ->filter(fn (self $type) => match ($parentType) {
                'initiative' => $type !== self::ProjectRecord,
                'project' => $type !== self::InitiativeRecord,
                default => false,
            })
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
