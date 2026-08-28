<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table): void {
            $table->timestamp('implemented_at')->nullable()->after('cancelled_at');
        });

        Schema::table('project_baselines', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_change_request_id')->nullable()->after('project_id');
            $table->unique(['organization_id', 'source_change_request_id'], 'project_baselines_source_change_unique');
            $table->foreign(['source_change_request_id', 'organization_id'], 'project_baselines_source_change_org_fk')
                ->references(['id', 'organization_id'])
                ->on('change_requests')
                ->restrictOnDelete();
        });

        Schema::create('change_request_implementations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('change_request_id');
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->string('status', 20)->default('planning');
            $table->text('plan_summary')->nullable();
            $table->text('execution_summary')->nullable();
            $table->text('verification_summary')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->string('contract_disposition', 40)->default('not_applicable');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->text('contract_justification')->nullable();
            $table->string('amendment_reference', 160)->nullable();
            $table->text('amendment_summary')->nullable();
            $table->date('amendment_effective_date')->nullable();
            $table->unsignedInteger('amendment_contract_version')->nullable();
            $table->string('baseline_disposition', 30)->default('create_new');
            $table->string('baseline_title', 160)->nullable();
            $table->text('baseline_justification')->nullable();
            $table->unsignedBigInteger('new_baseline_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->unique(['id', 'organization_id'], 'change_request_impl_id_org_unique');
            $table->unique(['organization_id', 'change_request_id'], 'change_request_impl_request_unique');
            $table->index(['organization_id', 'status'], 'change_request_impl_status_idx');
            $table->foreign('organization_id', 'change_request_impl_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'change_request_impl_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->foreign(['change_request_id', 'organization_id'], 'change_request_impl_request_org_fk')->references(['id', 'organization_id'])->on('change_requests')->restrictOnDelete();
            $table->foreign('responsible_id', 'change_request_impl_responsible_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['contract_id', 'organization_id'], 'change_request_impl_contract_org_fk')->references(['id', 'organization_id'])->on('project_contracts')->restrictOnDelete();
            $table->foreign(['new_baseline_id', 'organization_id'], 'change_request_impl_baseline_org_fk')->references(['id', 'organization_id'])->on('project_baselines')->restrictOnDelete();
            $table->foreign('completed_by', 'change_request_impl_completed_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by', 'change_request_impl_created_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by', 'change_request_impl_updated_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('change_request_implementation_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('change_request_id');
            $table->unsignedBigInteger('implementation_id');
            $table->string('event_type', 40);
            $table->unsignedBigInteger('actor_id');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');

            $table->unique(['id', 'organization_id'], 'change_request_impl_events_id_org_unique');
            $table->index(['change_request_id', 'occurred_at'], 'change_request_impl_events_request_idx');
            $table->foreign('organization_id', 'change_request_impl_events_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['change_request_id', 'organization_id'], 'change_request_impl_events_request_org_fk')->references(['id', 'organization_id'])->on('change_requests')->restrictOnDelete();
            $table->foreign(['implementation_id', 'organization_id'], 'change_request_impl_events_impl_org_fk')->references(['id', 'organization_id'])->on('change_request_implementations')->restrictOnDelete();
            $table->foreign('actor_id', 'change_request_impl_events_actor_fk')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_implementation_events');
        Schema::dropIfExists('change_request_implementations');

        Schema::table('project_baselines', function (Blueprint $table): void {
            $table->dropForeign('project_baselines_source_change_org_fk');
            $table->dropUnique('project_baselines_source_change_unique');
            $table->dropColumn('source_change_request_id');
        });

        Schema::table('change_requests', function (Blueprint $table): void {
            $table->dropColumn('implemented_at');
        });
    }
};
