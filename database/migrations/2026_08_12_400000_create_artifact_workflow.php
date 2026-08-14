<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifacts', function (Blueprint $table): void {
            $table->string('workflow_state', 30)->default('draft')->after('current_revision_sequence');
            $table->index(['organization_id', 'workflow_state'], 'artifacts_org_workflow_state_idx');
        });

        Schema::create('document_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('initiative_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20);
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->unsignedBigInteger('assigned_by');
            $table->timestamps();

            $table->foreign('organization_id', 'document_roles_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['initiative_id', 'organization_id'], 'document_roles_initiative_org_fk')->references(['id', 'organization_id'])->on('initiatives')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'document_roles_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->foreign('user_id', 'document_roles_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_by', 'document_roles_assigned_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['id', 'organization_id'], 'document_roles_id_org_unique');
            $table->index(['initiative_id', 'organization_id'], 'document_roles_initiative_org_idx');
            $table->index(['project_id', 'organization_id'], 'document_roles_project_org_idx');
            $table->index(['user_id', 'organization_id', 'role'], 'document_roles_user_org_role_idx');
            $table->index('assigned_by', 'document_roles_assigned_by_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_role_assignments ADD CONSTRAINT document_roles_exactly_one_parent_check CHECK ((initiative_id IS NOT NULL AND project_id IS NULL) OR (initiative_id IS NULL AND project_id IS NOT NULL))');
        }
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER document_roles_exactly_one_parent_insert BEFORE INSERT ON document_role_assignments FOR EACH ROW WHEN ((NEW.initiative_id IS NULL AND NEW.project_id IS NULL) OR (NEW.initiative_id IS NOT NULL AND NEW.project_id IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'document role requires exactly one parent'); END");
            DB::unprepared("CREATE TRIGGER document_roles_exactly_one_parent_update BEFORE UPDATE ON document_role_assignments FOR EACH ROW WHEN ((NEW.initiative_id IS NULL AND NEW.project_id IS NULL) OR (NEW.initiative_id IS NOT NULL AND NEW.project_id IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'document role requires exactly one parent'); END");
        }

        Schema::create('artifact_workflow_rounds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('artifact_id');
            $table->unsignedBigInteger('artifact_revision_id');
            $table->unsignedInteger('sequence');
            $table->string('state', 30);
            $table->unsignedBigInteger('submitted_by');
            $table->timestamp('submitted_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('source_initiative_configuration_version_id')->nullable();
            $table->unsignedBigInteger('source_project_configuration_version_id')->nullable();
            $table->string('applicability_outcome', 30);
            $table->string('applicability_reason_code', 100);
            $table->timestamps();

            $table->foreign('organization_id', 'artifact_rounds_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['artifact_id', 'organization_id'], 'artifact_rounds_artifact_org_fk')->references(['id', 'organization_id'])->on('artifacts')->restrictOnDelete();
            $table->foreign(['artifact_revision_id', 'organization_id'], 'artifact_rounds_revision_org_fk')->references(['id', 'organization_id'])->on('artifact_revisions')->restrictOnDelete();
            $table->foreign(['source_initiative_configuration_version_id', 'organization_id'], 'artifact_rounds_source_initiative_org_fk')->references(['id', 'organization_id'])->on('initiative_configuration_versions')->restrictOnDelete();
            $table->foreign(['source_project_configuration_version_id', 'organization_id'], 'artifact_rounds_source_project_org_fk')->references(['id', 'organization_id'])->on('project_configuration_versions')->restrictOnDelete();
            $table->foreign('submitted_by', 'artifact_rounds_submitted_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['artifact_id', 'sequence'], 'artifact_rounds_artifact_sequence_unique');
            $table->index(['artifact_id', 'organization_id'], 'artifact_rounds_artifact_org_idx');
            $table->index(['artifact_revision_id', 'organization_id'], 'artifact_rounds_revision_org_idx');
            $table->index(['source_initiative_configuration_version_id', 'organization_id'], 'artifact_rounds_source_initiative_org_idx');
            $table->index(['source_project_configuration_version_id', 'organization_id'], 'artifact_rounds_source_project_org_idx');
            $table->index('submitted_by', 'artifact_rounds_submitted_by_idx');
        });

        // A FK composta das decisões depende desta constraint. Ela é criada em
        // uma etapa separada para garantir que o PostgreSQL a materialize antes
        // da criação da tabela dependente.
        Schema::table('artifact_workflow_rounds', function (Blueprint $table): void {
            $table->unique(['id', 'organization_id'], 'artifact_rounds_id_org_unique');
        });

        Schema::create('artifact_workflow_decisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('round_id');
            $table->unsignedBigInteger('artifact_revision_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('role', 20);
            $table->string('decision', 30);
            $table->text('justification');
            $table->json('metadata')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->foreign('organization_id', 'artifact_decisions_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['round_id', 'organization_id'], 'artifact_decisions_round_org_fk')->references(['id', 'organization_id'])->on('artifact_workflow_rounds')->restrictOnDelete();
            $table->foreign(['artifact_revision_id', 'organization_id'], 'artifact_decisions_revision_org_fk')->references(['id', 'organization_id'])->on('artifact_revisions')->restrictOnDelete();
            $table->foreign('actor_id', 'artifact_decisions_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->index(['round_id', 'organization_id'], 'artifact_decisions_round_org_idx');
            $table->index(['artifact_revision_id', 'organization_id'], 'artifact_decisions_revision_org_idx');
            $table->index('actor_id', 'artifact_decisions_actor_idx');
        });

        $ruleSetId = DB::table('platform_applicability_rule_sets')->where('status', 'active')->whereNull('retired_at')->value('id');
        if ($ruleSetId !== null) {
            DB::table('platform_applicability_rules')->insert([
                'rule_set_id' => $ruleSetId,
                'key' => 'complete-document-approval-required',
                'priority' => 360,
                'target_type' => 'action',
                'target_key' => 'artifact.workflow.approval',
                'conditions' => json_encode([['field' => 'management_level', 'operator' => 'equals', 'value' => 'complete']], JSON_THROW_ON_ERROR),
                'outcome' => 'required',
                'reason_code' => 'COMPLETE_DOCUMENT_APPROVAL',
                'safe_explanation' => 'A aprovação documental segregada é obrigatória para governança completa.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('platform_applicability_rules')->where('key', 'complete-document-approval-required')->delete();
        Schema::dropIfExists('artifact_workflow_decisions');
        Schema::dropIfExists('artifact_workflow_rounds');
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS document_roles_exactly_one_parent_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS document_roles_exactly_one_parent_update');
        }
        Schema::dropIfExists('document_role_assignments');
        Schema::table('artifacts', function (Blueprint $table): void {
            $table->dropIndex('artifacts_org_workflow_state_idx');
            $table->dropColumn('workflow_state');
        });
    }
};
