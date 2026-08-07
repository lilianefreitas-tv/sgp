<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;

class DefaultDocumentTemplateProvisioner
{
    /** @return array<int, array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'MOD-001',
                'name' => 'Documento de Visão do SGP',
                'description' => 'Modelo institucional para contexto, problema, solução, objetivos, escopo e visão de futuro.',
                'type' => DocumentType::Vision,
            ],
            [
                'code' => 'MOD-002',
                'name' => 'Lista de Requisitos do SGP',
                'description' => 'Modelo consolidado de requisitos funcionais e não funcionais cadastrados no projeto.',
                'type' => DocumentType::RequirementsList,
            ],
            [
                'code' => 'MOD-003',
                'name' => 'Lista de Tarefas do SGP',
                'description' => 'Modelo consolidado das tarefas do projeto, seus vínculos, responsáveis, prioridades, prazos e situação.',
                'type' => DocumentType::TasksList,
            ],
            [
                'code' => 'MOD-004',
                'name' => 'Backlog Consolidado do Projeto',
                'description' => 'Modelo compacto que agrupa requisitos e respectivas tarefas, com seção própria para tarefas sem requisito vinculado.',
                'type' => DocumentType::ConsolidatedBacklog,
            ],
        ];
    }

    public function provision(int $organizationId): int
    {
        return DB::transaction(function () use ($organizationId): int {
            $created = 0;

            foreach (self::definitions() as $definition) {
                $exists = DocumentTemplate::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->where('type', $definition['type']->value)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $template = new DocumentTemplate([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'type' => $definition['type'],
                    'version' => 1,
                    'header_text' => 'Sistema de Gestão de Projetos de Software',
                    'footer_text' => 'Documento gerado automaticamente pelo SGP',
                    'is_active' => true,
                ]);
                $template->organization_id = $organizationId;
                $template->code = $definition['code'];
                $template->save();
                $created++;
            }

            return $created;
        });
    }
}
