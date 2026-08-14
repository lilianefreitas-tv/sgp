<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_baselines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedInteger('version');
            $table->string('title', 160);
            $table->text('justification');
            $table->timestamp('established_at');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->unique(['id', 'organization_id'], 'project_baselines_id_org_unique');
            $table->unique(['organization_id', 'project_id', 'version'], 'project_baselines_version_unique');
            $table->foreign(['project_id', 'organization_id'], 'project_baselines_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('project_baseline_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_baseline_id');
            $table->string('item_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->string('source_version', 40)->nullable();
            $table->string('code', 80)->nullable();
            $table->string('title', 240);
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['project_baseline_id', 'item_type', 'source_id'], 'project_baseline_items_source_unique');
            $table->foreign(['project_baseline_id', 'organization_id'], 'project_baseline_items_baseline_org_fk')->references(['id', 'organization_id'])->on('project_baselines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_baseline_items');
        Schema::dropIfExists('project_baselines');
    }
};
