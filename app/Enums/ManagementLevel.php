<?php

namespace App\Enums;

enum ManagementLevel: string
{
    case Simplified = 'simplified';
    case Intermediate = 'intermediate';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Simplified => 'Simplificado',
            self::Intermediate => 'Intermediário',
            self::Complete => 'Completo',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Simplified => 'Indicado para protótipos, MVPs e projetos pequenos, com formalização essencial.',
            self::Intermediate => 'Indicado para projetos institucionais ou comerciais que exigem controle formal.',
            self::Complete => 'Indicado para projetos críticos, contratos complexos e ambientes auditáveis.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $level) => [$level->value => $level->label()])
            ->all();
    }
}
