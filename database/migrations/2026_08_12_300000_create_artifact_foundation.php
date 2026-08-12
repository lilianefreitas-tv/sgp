<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->unsignedBigInteger('next_artifact_number')->default(1);
        });

        Schema::create('artifacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('initiative_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('code', 24);
            $table->string('type', 40);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('current_revision_sequence')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'artifacts_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('created_by', 'artifacts_created_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['initiative_id', 'organization_id'], 'artifacts_initiative_org_fk')->references(['id', 'organization_id'])->on('initiatives')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'artifacts_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->unique(['organization_id', 'code'], 'artifacts_org_code_unique');
            $table->unique(['id', 'organization_id'], 'artifacts_id_org_unique');
            $table->index(['initiative_id', 'organization_id'], 'artifacts_initiative_org_idx');
            $table->index(['project_id', 'organization_id'], 'artifacts_project_org_idx');
            $table->index('created_by', 'artifacts_created_by_idx');
        });

        $this->enforceExactlyOneParent();

        Schema::create('artifact_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('artifact_id');
            $table->unsignedInteger('sequence');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('content');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('source_initiative_configuration_version_id')->nullable();
            $table->unsignedBigInteger('source_project_configuration_version_id')->nullable();
            $table->string('checksum', 64);
            $table->unsignedBigInteger('changed_by');
            $table->text('change_reason');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('organization_id', 'artifact_revisions_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('changed_by', 'artifact_revisions_changed_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['artifact_id', 'organization_id'], 'artifact_revisions_artifact_org_fk')->references(['id', 'organization_id'])->on('artifacts')->restrictOnDelete();
            $table->foreign(['source_initiative_configuration_version_id', 'organization_id'], 'artifact_revisions_initiative_config_org_fk')->references(['id', 'organization_id'])->on('initiative_configuration_versions')->restrictOnDelete();
            $table->foreign(['source_project_configuration_version_id', 'organization_id'], 'artifact_revisions_project_config_org_fk')->references(['id', 'organization_id'])->on('project_configuration_versions')->restrictOnDelete();
            $table->unique(['artifact_id', 'sequence'], 'artifact_revisions_artifact_sequence_unique');
            $table->unique(['id', 'organization_id'], 'artifact_revisions_id_org_unique');
            $table->index('organization_id', 'artifact_revisions_org_idx');
            $table->index(['artifact_id', 'organization_id'], 'artifact_revisions_artifact_org_idx');
            $table->index(['source_initiative_configuration_version_id', 'organization_id'], 'artifact_revisions_initiative_config_org_idx');
            $table->index(['source_project_configuration_version_id', 'organization_id'], 'artifact_revisions_project_config_org_idx');
            $table->index('changed_by', 'artifact_revisions_changed_by_idx');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS artifacts_exactly_one_parent_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS artifacts_exactly_one_parent_update');
        }
        Schema::dropIfExists('artifact_revisions');
        Schema::dropIfExists('artifacts');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('next_artifact_number'));
    }

    private function enforceExactlyOneParent(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE artifacts ADD CONSTRAINT artifacts_exactly_one_parent_check CHECK ((initiative_id IS NOT NULL AND project_id IS NULL) OR (initiative_id IS NULL AND project_id IS NOT NULL))');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            foreach (['insert', 'update'] as $event) {
                DB::unprepared("CREATE TRIGGER artifacts_exactly_one_parent_{$event} BEFORE {$event} ON artifacts FOR EACH ROW WHEN ((NEW.initiative_id IS NULL AND NEW.project_id IS NULL) OR (NEW.initiative_id IS NOT NULL AND NEW.project_id IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'artifacts requires exactly one parent'); END");
            }
        }
    }
};
