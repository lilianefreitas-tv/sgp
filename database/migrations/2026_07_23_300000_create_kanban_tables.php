<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 150)->default('Quadro Kanban');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kanban_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanban_board_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('name', 100);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['kanban_board_id', 'status']);
            $table->unique(['kanban_board_id', 'position']);
        });

        Schema::create('kanban_task_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('kanban_column_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->index(['kanban_column_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_task_positions');
        Schema::dropIfExists('kanban_columns');
        Schema::dropIfExists('kanban_boards');
    }
};
