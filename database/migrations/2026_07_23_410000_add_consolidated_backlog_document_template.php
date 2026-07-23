<?php

use App\Enums\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('document_templates')->where('type', DocumentType::ConsolidatedBacklog->value)->exists()) {
            return;
        }

        $sequence = ((int) DB::table('document_templates')->max('id')) + 1;
        do {
            $code = 'MOD-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (DB::table('document_templates')->where('code', $code)->exists());

        DB::table('document_templates')->insert([
            'code' => $code,
            'name' => 'Backlog Consolidado do Projeto',
            'description' => 'Modelo compacto que agrupa requisitos e respectivas tarefas, com seção própria para tarefas sem requisito vinculado.',
            'type' => DocumentType::ConsolidatedBacklog->value,
            'version' => 1,
            'header_text' => 'Sistema de Gestão de Projetos de Software',
            'footer_text' => 'Documento gerado automaticamente pelo SGP',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('project_documents')
            ->where('type', DocumentType::ConsolidatedBacklog->value)
            ->delete();

        DB::table('document_templates')
            ->where('type', DocumentType::ConsolidatedBacklog->value)
            ->delete();
    }
};
