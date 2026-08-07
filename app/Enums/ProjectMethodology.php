<?php

namespace App\Enums;

enum ProjectMethodology: string
{
    case Kanban = 'kanban';
    case Scrum = 'scrum';
    case Hybrid = 'hybrid';
    case Traditional = 'traditional';

    public function label(): string
    {
        return match ($this) {
            self::Kanban => 'Kanban',
            self::Scrum => 'Scrum',
            self::Hybrid => 'Híbrida',
            self::Traditional => 'Tradicional',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Kanban => 'Organiza o fluxo contínuo de trabalho por etapas visuais.',
            self::Scrum => 'Organiza a execução em ciclos curtos e planejados.',
            self::Hybrid => 'Combina práticas de diferentes métodos conforme o contexto.',
            self::Traditional => 'Organiza o trabalho em etapas sequenciais e previamente planejadas.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $methodology) => [$methodology->value => $methodology->label()])
            ->all();
    }
}
