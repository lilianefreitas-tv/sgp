<?php

namespace App\Enums;

enum ManagementLevel: string
{
    /** @deprecated Mantido apenas para reexecução das migrations da BL-SGP-002. */
    case Simplified = 'simplified';
    case Essential = 'essential';
    case Intermediate = 'intermediate';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Simplified => 'Simplificado',
            self::Essential => 'Essencial',
            self::Intermediate => 'Intermediário',
            self::Complete => 'Completo',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Simplified => 'Indicado para protótipos, MVPs e projetos pequenos, com formalização essencial.',
            self::Essential => 'Indicado para iniciativas e projetos que exigem a formalização essencial.',
            self::Intermediate => 'Indicado para projetos institucionais ou comerciais que exigem controle formal.',
            self::Complete => 'Indicado para projetos críticos, contratos complexos e ambientes auditáveis.',
        };
    }

    /** @return list<string> */
    public static function currentValues(): array
    {
        return array_keys(self::options());
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->reject(fn (self $level) => $level === self::Simplified)
            ->mapWithKeys(fn (self $level) => [$level->value => $level->label()])
            ->all();
    }
}
