<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('origin_type', 30)->default('direct')->after('initiative_id');
            $table->index(['organization_id', 'origin_type'], 'projects_org_origin_idx');
        });

        DB::table('projects')->whereNotNull('initiative_id')->update(['origin_type' => 'initiative']);

        Schema::table('project_attachments', function (Blueprint $table): void {
            $table->boolean('is_origin_document')->default(false)->after('context_id');
            $table->uuid('origin_series_uuid')->nullable()->after('is_origin_document');
            $table->string('origin_category', 40)->nullable()->after('origin_series_uuid');
            $table->string('origin_title', 200)->nullable()->after('origin_category');
            $table->string('external_reference', 150)->nullable()->after('origin_title');
            $table->date('original_document_date')->nullable()->after('external_reference');
            $table->string('declared_version', 40)->nullable()->after('original_document_date');
            $table->unsignedInteger('origin_version')->nullable()->after('declared_version');
            $table->string('origin_status', 20)->nullable()->after('origin_version');
            $table->foreignId('replaces_attachment_id')->nullable()->after('origin_status')->constrained('project_attachments')->restrictOnDelete();
            $table->index(['organization_id', 'project_id', 'is_origin_document'], 'attachments_org_project_origin_idx');
            $table->unique(['organization_id', 'project_id', 'origin_series_uuid', 'origin_version'], 'attachments_origin_version_unique');
        });

        Schema::create('project_origin_baselines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('established_by');
            $table->string('code', 40);
            $table->string('purpose', 300)->nullable();
            $table->string('checksum', 64);
            $table->timestamp('established_at');
            $table->timestamps();
            $table->unique(['id', 'organization_id'], 'origin_baselines_id_org_unique');
            $table->unique(['organization_id', 'project_id'], 'origin_baselines_project_unique');
            $table->foreign('organization_id', 'origin_baselines_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'origin_baselines_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->foreign('established_by', 'origin_baselines_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->index(['project_id', 'organization_id'], 'origin_baselines_project_org_idx');
            $table->index('established_by', 'origin_baselines_actor_idx');
        });

        Schema::create('project_origin_baseline_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('baseline_id');
            $table->unsignedBigInteger('project_attachment_id');
            $table->timestamps();
            $table->unique(['organization_id', 'baseline_id', 'project_attachment_id'], 'origin_baseline_items_unique');
            $table->foreign('organization_id', 'origin_baseline_items_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['baseline_id', 'organization_id'], 'origin_baseline_items_baseline_org_fk')->references(['id', 'organization_id'])->on('project_origin_baselines')->restrictOnDelete();
            $table->foreign('project_attachment_id', 'origin_baseline_items_attachment_fk')->references('id')->on('project_attachments')->restrictOnDelete();
            $table->index(['baseline_id', 'organization_id'], 'origin_baseline_items_baseline_org_idx');
            $table->index(['project_attachment_id', 'organization_id'], 'origin_baseline_items_attachment_org_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_origin_baseline_items');
        Schema::dropIfExists('project_origin_baselines');

        Schema::table('project_attachments', function (Blueprint $table): void {
            $table->dropForeign(['replaces_attachment_id']);
            $table->dropIndex('attachments_org_project_origin_idx');
            $table->dropUnique('attachments_origin_version_unique');
            $table->dropColumn(['is_origin_document', 'origin_series_uuid', 'origin_category', 'origin_title', 'external_reference', 'original_document_date', 'declared_version', 'origin_version', 'origin_status', 'replaces_attachment_id']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex('projects_org_origin_idx');
            $table->dropColumn('origin_type');
        });
    }
};
