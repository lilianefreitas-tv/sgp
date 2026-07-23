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
        Schema::table('projects', function (Blueprint $table) {
            $table->text('document_context')->nullable();
            $table->text('problem_statement')->nullable();
            $table->text('solution_summary')->nullable();
            $table->text('target_audience')->nullable();
            $table->text('scope_included')->nullable();
            $table->text('scope_excluded')->nullable();
            $table->text('assumptions')->nullable();
            $table->text('constraints')->nullable();
            $table->text('success_criteria')->nullable();
            $table->text('future_vision')->nullable();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('type', 40);
            $table->unsignedInteger('version')->default(1);
            $table->string('header_text', 180)->nullable();
            $table->string('footer_text', 180)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });

        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->constrained('document_templates')->restrictOnDelete();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 40);
            $table->string('title', 200);
            $table->unsignedInteger('version');
            $table->string('docx_path', 500);
            $table->string('pdf_path', 500);
            $table->string('docx_file_name', 255);
            $table->string('pdf_file_name', 255);
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['project_id', 'type', 'version']);
            $table->index(['project_id', 'generated_at']);
        });

        $now = now();
        DB::table('document_templates')->insert([
            [
                'code' => 'MOD-001',
                'name' => 'Documento de Visão do SGP',
                'description' => 'Modelo institucional para contexto, problema, solução, objetivos, escopo e visão de futuro.',
                'type' => DocumentType::Vision->value,
                'version' => 1,
                'header_text' => 'Sistema de Gestão de Projetos de Software',
                'footer_text' => 'Documento gerado automaticamente pelo SGP',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'MOD-002',
                'name' => 'Lista de Requisitos do SGP',
                'description' => 'Modelo consolidado de requisitos funcionais e não funcionais cadastrados no projeto.',
                'type' => DocumentType::RequirementsList->value,
                'version' => 1,
                'header_text' => 'Sistema de Gestão de Projetos de Software',
                'footer_text' => 'Documento gerado automaticamente pelo SGP',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'MOD-003',
                'name' => 'Lista de Tarefas do SGP',
                'description' => 'Modelo consolidado das tarefas do projeto, seus vínculos, responsáveis, prioridades, prazos e situação.',
                'type' => DocumentType::TasksList->value,
                'version' => 1,
                'header_text' => 'Sistema de Gestão de Projetos de Software',
                'footer_text' => 'Documento gerado automaticamente pelo SGP',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
        Schema::dropIfExists('document_templates');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'document_context',
                'problem_statement',
                'solution_summary',
                'target_audience',
                'scope_included',
                'scope_excluded',
                'assumptions',
                'constraints',
                'success_criteria',
                'future_vision',
            ]);
        });
    }
};
