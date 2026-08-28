<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedInteger('sequence');
            $table->string('code', 40);
            $table->string('origin', 40);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('justification')->nullable();
            $table->string('urgency', 20)->nullable();
            $table->unsignedBigInteger('baseline_id')->nullable();
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('analyst_id')->nullable();
            $table->string('state', 30)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('analysis_started_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->unique(['id', 'organization_id'], 'change_requests_id_org_unique');
            $table->unique(['organization_id', 'project_id', 'sequence'], 'change_requests_project_sequence_unique');
            $table->unique(['organization_id', 'project_id', 'code'], 'change_requests_project_code_unique');
            $table->index(['organization_id', 'project_id', 'state'], 'change_requests_project_state_idx');
            $table->index(['organization_id', 'requester_id'], 'change_requests_requester_idx');
            $table->index(['organization_id', 'analyst_id'], 'change_requests_analyst_idx');

            $table->foreign('organization_id', 'change_requests_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'change_requests_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->foreign(['baseline_id', 'organization_id'], 'change_requests_baseline_org_fk')->references(['id', 'organization_id'])->on('project_baselines')->restrictOnDelete();
            $table->foreign('requester_id', 'change_requests_requester_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('analyst_id', 'change_requests_analyst_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by', 'change_requests_creator_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by', 'change_requests_updater_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('change_request_affected_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('change_request_id');
            $table->string('item_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('code', 80)->nullable();
            $table->string('title', 240);
            $table->timestamps();

            $table->unique(['change_request_id', 'item_type', 'source_id'], 'change_request_items_source_unique');
            $table->index(['organization_id', 'item_type', 'source_id'], 'change_request_items_source_idx');
            $table->foreign('organization_id', 'change_request_items_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['change_request_id', 'organization_id'], 'change_request_items_request_org_fk')->references(['id', 'organization_id'])->on('change_requests')->restrictOnDelete();
        });

        Schema::create('change_request_transitions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('change_request_id');
            $table->string('from_state', 30)->nullable();
            $table->string('to_state', 30);
            $table->unsignedBigInteger('actor_id');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['change_request_id', 'occurred_at'], 'change_request_transitions_time_idx');
            $table->index(['organization_id', 'actor_id'], 'change_request_transitions_actor_idx');
            $table->foreign('organization_id', 'change_request_transitions_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['change_request_id', 'organization_id'], 'change_request_transitions_request_org_fk')->references(['id', 'organization_id'])->on('change_requests')->restrictOnDelete();
            $table->foreign('actor_id', 'change_request_transitions_actor_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('project_attachments', function (Blueprint $table): void {
            $table->string('attachment_kind', 20)->nullable()->after('context_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_attachments', function (Blueprint $table): void {
            $table->dropColumn('attachment_kind');
        });

        Schema::dropIfExists('change_request_transitions');
        Schema::dropIfExists('change_request_affected_items');
        Schema::dropIfExists('change_requests');
    }
};
