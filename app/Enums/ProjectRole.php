<?php

namespace App\Enums;

enum ProjectRole: string
{
    case ProjectManager = 'project_manager';
    case RequirementsAnalyst = 'requirements_analyst';
    case Developer = 'developer';
    case Tester = 'tester';
    case Dba = 'dba';
    case Client = 'client';
    case Validator = 'validator';
    case Observer = 'observer';

    public function label(): string
    {
        return match ($this) {
            self::ProjectManager => 'Gerente de Projetos',
            self::RequirementsAnalyst => 'Analista de Requisitos',
            self::Developer => 'Desenvolvedor',
            self::Tester => 'Testador',
            self::Dba => 'DBA',
            self::Client => 'Cliente',
            self::Validator => 'Validador',
            self::Observer => 'Observador',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
