<?php

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 30);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 40)->default(RequirementType::Functional->value);
            $table->string('priority', 30)->default(RequirementPriority::Medium->value);
            $table->string('status', 40)->default(RequirementStatus::Proposed->value);
            $table->text('acceptance_criteria')->nullable();
            $table->string('source', 150)->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'status', 'is_active']);
            $table->index(['project_id', 'priority']);
            $table->index('responsible_id');
        });

        Schema::create('requirement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('change_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['requirement_id', 'version_number']);
        });

        Schema::create('requirement_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_requirement_id')->constrained('requirements')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['requirement_id', 'depends_on_requirement_id'], 'requirement_dependency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_dependencies');
        Schema::dropIfExists('requirement_versions');
        Schema::dropIfExists('requirements');
    }
};
