<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifact_publications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('artifact_id');
            $table->unsignedBigInteger('artifact_revision_id');
            $table->unsignedBigInteger('artifact_workflow_round_id');
            $table->unsignedInteger('sequence');
            $table->string('status', 20);
            $table->string('disk', 50)->default('local');
            $table->string('package_path', 1024);
            $table->string('package_checksum', 64);
            $table->json('manifest');
            $table->unsignedBigInteger('published_by');
            $table->timestamp('published_at');
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'artifact_publications_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['artifact_id', 'organization_id'], 'artifact_publications_artifact_org_fk')->references(['id', 'organization_id'])->on('artifacts')->restrictOnDelete();
            $table->foreign(['artifact_revision_id', 'organization_id'], 'artifact_publications_revision_org_fk')->references(['id', 'organization_id'])->on('artifact_revisions')->restrictOnDelete();
            $table->foreign(['artifact_workflow_round_id', 'organization_id'], 'artifact_publications_round_org_fk')->references(['id', 'organization_id'])->on('artifact_workflow_rounds')->restrictOnDelete();
            $table->foreign('published_by', 'artifact_publications_publisher_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('revoked_by', 'artifact_publications_revoker_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['id', 'organization_id'], 'artifact_publications_id_org_unique');
            $table->unique(['artifact_id', 'sequence'], 'artifact_publications_artifact_sequence_unique');
            $table->unique(['artifact_revision_id', 'artifact_workflow_round_id'], 'artifact_publications_revision_round_unique');
            $table->index(['artifact_id', 'organization_id'], 'artifact_publications_artifact_org_idx');
            $table->index(['artifact_revision_id', 'organization_id'], 'artifact_publications_revision_org_idx');
            $table->index(['artifact_workflow_round_id', 'organization_id'], 'artifact_publications_round_org_idx');
            $table->index(['organization_id', 'status'], 'artifact_publications_org_status_idx');
            $table->index('published_by', 'artifact_publications_publisher_idx');
            $table->index('revoked_by', 'artifact_publications_revoker_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_publications');
    }
};
