<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'clients',
        'projects',
        'project_user',
        'requirements',
        'requirement_versions',
        'requirement_dependencies',
        'tasks',
        'task_histories',
        'kanban_boards',
        'kanban_columns',
        'kanban_task_positions',
        'document_templates',
        'project_documents',
        'project_comments',
        'project_attachments',
        'project_activities',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('organization_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('organization_id');
            });
        }
    }
};
