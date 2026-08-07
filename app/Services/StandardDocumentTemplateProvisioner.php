<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class StandardDocumentTemplateProvisioner
{
    /** @return array<int, array{code: string, name: string, description: string, type: DocumentType}> */
    private function definitions(): array
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

    public function provision(Organization $organization, ?int $createdBy = null): int
    {
        return DB::transaction(function () use ($organization, $createdBy): int {
            $created = 0;

            foreach ($this->definitions() as $definition) {
                $exists = DocumentTemplate::withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('type', $definition['type']->value)
                    ->where('is_active', true)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $template = new DocumentTemplate([
                    'created_by' => $createdBy,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'type' => $definition['type'],
                    'version' => 1,
                    'header_text' => 'Sistema de Gestão de Projetos de Software',
                    'footer_text' => 'Documento gerado automaticamente pelo SGP',
                    'is_active' => true,
                ]);
                $template->organization_id = $organization->id;
                $template->code = $this->availableCode($organization->id, $definition['code']);
                $template->save();
                $created++;
            }

            return $created;
        });
    }

    private function availableCode(int $organizationId, string $preferred): string
    {
        $used = DocumentTemplate::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('code', $preferred)
            ->exists();

        if (! $used) {
            return $preferred;
        }

        $sequence = 1;
        do {
            $candidate = 'MOD-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (DocumentTemplate::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('code', $candidate)
            ->exists());

        return $candidate;
    }
}
