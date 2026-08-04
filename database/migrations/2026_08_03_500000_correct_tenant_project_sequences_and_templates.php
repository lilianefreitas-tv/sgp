<?php

use App\Enums\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->unsignedBigInteger('next_project_number')->default(1);
        });

        DB::transaction(function (): void {
            $organizations = DB::table('organizations')->orderBy('id')->pluck('id');

            foreach ($organizations as $organizationId) {
                $projects = DB::table('projects')
                    ->where('organization_id', $organizationId)
                    ->orderBy('id')
                    ->get(['id']);

                foreach ($projects->values() as $index => $project) {
                    DB::table('projects')
                        ->where('id', $project->id)
                        ->update(['code' => '~'.str_pad((string) ($index + 1), 18, '0', STR_PAD_LEFT)]);
                }

                foreach ($projects->values() as $index => $project) {
                    DB::table('projects')
                        ->where('id', $project->id)
                        ->update([
                            'code' => 'PRJ-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                            'updated_at' => now(),
                        ]);
                }

                DB::table('organizations')
                    ->where('id', $organizationId)
                    ->update([
                        'next_project_number' => $projects->count() + 1,
                        'updated_at' => now(),
                    ]);

                $this->provisionDefaultTemplates((int) $organizationId);
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('next_project_number');
        });
    }

    private function provisionDefaultTemplates(int $organizationId): void
    {
        $definitions = [
            ['MOD-001', 'Documento de Visão do SGP', 'Modelo institucional para contexto, problema, solução, objetivos, escopo e visão de futuro.', DocumentType::Vision->value],
            ['MOD-002', 'Lista de Requisitos do SGP', 'Modelo consolidado de requisitos funcionais e não funcionais cadastrados no projeto.', DocumentType::RequirementsList->value],
            ['MOD-003', 'Lista de Tarefas do SGP', 'Modelo consolidado das tarefas do projeto, seus vínculos, responsáveis, prioridades, prazos e situação.', DocumentType::TasksList->value],
            ['MOD-004', 'Backlog Consolidado do Projeto', 'Modelo compacto que agrupa requisitos e respectivas tarefas, com seção própria para tarefas sem requisito vinculado.', DocumentType::ConsolidatedBacklog->value],
        ];

        foreach ($definitions as [$code, $name, $description, $type]) {
            if (DB::table('document_templates')
                ->where('organization_id', $organizationId)
                ->where('type', $type)
                ->exists()) {
                continue;
            }

            DB::table('document_templates')->insert([
                'organization_id' => $organizationId,
                'created_by' => null,
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'version' => 1,
                'header_text' => 'Sistema de Gestão de Projetos de Software',
                'footer_text' => 'Documento gerado automaticamente pelo SGP',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
