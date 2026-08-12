<?php

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeState;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->unsignedBigInteger('next_initiative_number')->default(1);
        });

        Schema::create('initiatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('title', 200);
            $table->text('context')->nullable();
            $table->string('origin', 30);
            $table->string('state', 30)->default(InitiativeState::Draft->value);
            $table->string('execution_nature', 30)->default(ExecutionNature::Internal->value);
            $table->string('financial_management_mode', 30)->default(FinancialManagementMode::NotApplicable->value);
            $table->string('management_level', 30)->default(ManagementLevel::Essential->value);
            $table->string('methodology', 80)->default(ProjectMethodology::Kanban->value);
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'initiatives_org_code_unique');
            $table->unique(['id', 'organization_id'], 'initiatives_id_org_unique');
            $table->index(['organization_id', 'state'], 'initiatives_org_state_idx');
            $table->index(['organization_id', 'origin'], 'initiatives_org_origin_idx');
        });

        Schema::create('initiative_configuration_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('initiative_id');
            $table->unsignedInteger('sequence');
            $table->string('origin', 30);
            $table->string('execution_nature', 30);
            $table->string('financial_management_mode', 30);
            $table->string('management_level', 30);
            $table->string('methodology', 80);
            $table->timestamp('effective_from');
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('justification');
            $table->json('applicability_impact')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->foreign('organization_id', 'initiative_versions_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['initiative_id', 'organization_id'], 'initiative_versions_parent_org_fk')->references(['id', 'organization_id'])->on('initiatives')->restrictOnDelete();
            $table->unique(['initiative_id', 'sequence'], 'initiative_versions_sequence_unique');
            $table->unique(['id', 'organization_id'], 'initiative_versions_id_org_unique');
            $table->index(['initiative_id', 'organization_id'], 'initiative_versions_parent_org_idx');
            $table->index(['initiative_id', 'effective_from'], 'initiative_versions_time_idx');
        });

        Schema::create('project_configuration_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedInteger('sequence');
            $table->string('execution_nature', 30);
            $table->string('financial_management_mode', 30);
            $table->string('management_level', 30);
            $table->string('methodology', 80);
            $table->unsignedBigInteger('source_initiative_configuration_version_id')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('justification');
            $table->json('applicability_impact')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->foreign('organization_id', 'project_versions_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'project_versions_parent_org_fk')->references(['id', 'organization_id'])->on('projects')->cascadeOnDelete();
            $table->foreign(['source_initiative_configuration_version_id', 'organization_id'], 'project_versions_source_org_fk')->references(['id', 'organization_id'])->on('initiative_configuration_versions')->restrictOnDelete();
            $table->unique(['project_id', 'sequence'], 'project_versions_sequence_unique');
            $table->index(['project_id', 'organization_id'], 'project_versions_parent_org_idx');
            $table->index(['source_initiative_configuration_version_id', 'organization_id'], 'project_versions_source_org_idx');
            $table->index(['project_id', 'effective_from'], 'project_versions_time_idx');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedBigInteger('initiative_id')->nullable();
            $table->unsignedBigInteger('source_initiative_configuration_version_id')->nullable();
            // projects_initiative_unique covers the initiative_id prefix, so a duplicate composite index is unnecessary.
            $table->unique('initiative_id', 'projects_initiative_unique');
            $table->index(['source_initiative_configuration_version_id', 'organization_id'], 'projects_source_initiative_version_org_idx');
            $table->foreign(['initiative_id', 'organization_id'], 'projects_initiative_org_fk')->references(['id', 'organization_id'])->on('initiatives')->restrictOnDelete();
            $table->foreign(['source_initiative_configuration_version_id', 'organization_id'], 'projects_source_initiative_version_org_fk')->references(['id', 'organization_id'])->on('initiative_configuration_versions')->restrictOnDelete();
        });

        DB::table('projects')->where('management_level', ManagementLevel::Simplified->value)
            ->update(['management_level' => ManagementLevel::Essential->value]);
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('management_level', 30)->default(ManagementLevel::Essential->value)->change();
        });
    }

    public function down(): void
    {
        // Intended only before later functional use. Production rollback after official data requires a controlled plan.
        DB::table('projects')->where('management_level', ManagementLevel::Essential->value)
            ->update(['management_level' => ManagementLevel::Simplified->value]);
        $isSqlite = DB::getDriverName() === 'sqlite';
        Schema::table('projects', function (Blueprint $table) use ($isSqlite): void {
            $table->dropForeign($isSqlite
                ? ['source_initiative_configuration_version_id', 'organization_id']
                : 'projects_source_initiative_version_org_fk');
            $table->dropForeign($isSqlite
                ? ['initiative_id', 'organization_id']
                : 'projects_initiative_org_fk');
            $table->dropUnique('projects_initiative_unique');
            $table->dropIndex('projects_source_initiative_version_org_idx');
            $table->dropColumn(['initiative_id', 'source_initiative_configuration_version_id']);
            $table->string('management_level', 30)->default(ManagementLevel::Simplified->value)->change();
        });
        Schema::dropIfExists('project_configuration_versions');
        Schema::dropIfExists('initiative_configuration_versions');
        Schema::dropIfExists('initiatives');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('next_initiative_number'));
    }
};
