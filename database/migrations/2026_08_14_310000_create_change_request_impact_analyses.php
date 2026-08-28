<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_request_impact_analyses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('change_request_id');
            $table->unsignedInteger('round');
            $table->unsignedBigInteger('analyst_id');
            $table->string('status', 20)->default('draft');
            $table->string('classification', 30)->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->string('recommendation', 40)->nullable();
            $table->text('executive_summary')->nullable();
            $table->text('scope_impact')->nullable();
            $table->text('requirements_impact')->nullable();
            $table->text('technical_impact')->nullable();
            $table->text('data_impact')->nullable();
            $table->text('security_impact')->nullable();
            $table->text('schedule_impact')->nullable();
            $table->text('resources_impact')->nullable();
            $table->text('cost_impact')->nullable();
            $table->text('contract_impact')->nullable();
            $table->text('quality_impact')->nullable();
            $table->text('testing_impact')->nullable();
            $table->text('operations_impact')->nullable();
            $table->text('documentation_impact')->nullable();
            $table->text('risks_and_mitigations')->nullable();
            $table->decimal('estimated_effort_hours', 10, 2)->nullable();
            $table->unsignedInteger('estimated_schedule_days')->nullable();
            $table->decimal('estimated_cost_amount', 15, 2)->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->unique(['id', 'organization_id'], 'change_request_analyses_id_org_unique');
            $table->unique(['change_request_id', 'round'], 'change_request_analyses_round_unique');
            $table->index(['organization_id', 'status'], 'change_request_analyses_status_idx');
            $table->foreign('organization_id', 'change_request_analyses_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['change_request_id', 'organization_id'], 'change_request_analyses_request_org_fk')->references(['id', 'organization_id'])->on('change_requests')->restrictOnDelete();
            $table->foreign('analyst_id', 'change_request_analyses_analyst_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('completed_by', 'change_request_analyses_completed_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by', 'change_request_analyses_created_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by', 'change_request_analyses_updated_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        DB::table('change_requests')
            ->where('state', 'under_analysis')
            ->orderBy('id')
            ->each(function (object $changeRequest): void {
                $actorId = $changeRequest->analyst_id ?: $changeRequest->updated_by;
                DB::table('change_request_impact_analyses')->insert([
                    'organization_id' => $changeRequest->organization_id,
                    'change_request_id' => $changeRequest->id,
                    'round' => 1,
                    'analyst_id' => $actorId,
                    'status' => 'draft',
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_impact_analyses');
    }
};
