<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_test_cases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedInteger('sequence');
            $table->string('code', 40);
            $table->string('title', 200);
            $table->text('objective');
            $table->text('preconditions')->nullable();
            $table->text('test_data')->nullable();
            $table->text('steps');
            $table->text('expected_result');
            $table->string('severity', 20);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('assigned_tester_id')->nullable();
            $table->unsignedBigInteger('requirement_id')->nullable();
            $table->unsignedBigInteger('change_request_id')->nullable();
            $table->unsignedBigInteger('baseline_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->unique(['id', 'organization_id'], 'project_test_cases_id_org_unique');
            $table->unique(['organization_id', 'project_id', 'sequence'], 'project_test_cases_sequence_unique');
            $table->unique(['organization_id', 'project_id', 'code'], 'project_test_cases_code_unique');
            $table->index(['organization_id', 'project_id', 'status'], 'project_test_cases_status_idx');
            $table->foreign('organization_id', 'project_test_cases_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'project_test_cases_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->foreign('assigned_tester_id', 'project_test_cases_tester_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['requirement_id', 'organization_id'], 'project_test_cases_requirement_org_fk')->references(['id', 'organization_id'])->on('requirements')->restrictOnDelete();
            $table->foreign(['change_request_id', 'organization_id'], 'project_test_cases_change_org_fk')->references(['id', 'organization_id'])->on('change_requests')->restrictOnDelete();
            $table->foreign(['baseline_id', 'organization_id'], 'project_test_cases_baseline_org_fk')->references(['id', 'organization_id'])->on('project_baselines')->restrictOnDelete();
            $table->foreign('created_by', 'project_test_cases_creator_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by', 'project_test_cases_updater_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('test_executions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('test_case_id');
            $table->unsignedInteger('execution_number');
            $table->string('result', 20);
            $table->json('case_snapshot');
            $table->string('environment', 200);
            $table->text('observed_result');
            $table->text('notes')->nullable();
            $table->string('defect_reference', 150)->nullable();
            $table->unsignedBigInteger('executed_by');
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->unique(['id', 'organization_id'], 'test_executions_id_org_unique');
            $table->unique(['test_case_id', 'execution_number'], 'test_executions_number_unique');
            $table->index(['organization_id', 'result', 'executed_at'], 'test_executions_result_idx');
            $table->foreign('organization_id', 'test_executions_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['test_case_id', 'organization_id'], 'test_executions_case_org_fk')->references(['id', 'organization_id'])->on('project_test_cases')->restrictOnDelete();
            $table->foreign('executed_by', 'test_executions_actor_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('test_evidences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('test_execution_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('disk', 40);
            $table->string('path', 700);
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['id', 'organization_id'], 'test_evidences_id_org_unique');
            $table->index(['test_execution_id', 'created_at'], 'test_evidences_execution_idx');
            $table->foreign('organization_id', 'test_evidences_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['test_execution_id', 'organization_id'], 'test_evidences_execution_org_fk')->references(['id', 'organization_id'])->on('test_executions')->restrictOnDelete();
            $table->foreign('uploaded_by', 'test_evidences_uploader_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('project_homologations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedInteger('sequence');
            $table->string('code', 40);
            $table->string('title', 200);
            $table->string('status', 40);
            $table->unsignedBigInteger('baseline_id')->nullable();
            $table->string('commit_reference', 120)->nullable();
            $table->string('environment', 200);
            $table->text('scope');
            $table->text('decision_notes');
            $table->json('summary');
            $table->unsignedBigInteger('decided_by');
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(['id', 'organization_id'], 'project_homologations_id_org_unique');
            $table->unique(['organization_id', 'project_id', 'sequence'], 'project_homologations_sequence_unique');
            $table->unique(['organization_id', 'project_id', 'code'], 'project_homologations_code_unique');
            $table->index(['organization_id', 'project_id', 'status'], 'project_homologations_status_idx');
            $table->foreign('organization_id', 'project_homologations_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'project_homologations_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->foreign(['baseline_id', 'organization_id'], 'project_homologations_baseline_org_fk')->references(['id', 'organization_id'])->on('project_baselines')->restrictOnDelete();
            $table->foreign('decided_by', 'project_homologations_decider_fk')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_homologations');
        Schema::dropIfExists('test_evidences');
        Schema::dropIfExists('test_executions');
        Schema::dropIfExists('project_test_cases');
    }
};
