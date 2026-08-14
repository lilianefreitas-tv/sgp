<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_contracts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('initiative_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('code', 40);
            $table->string('title', 200);
            $table->string('contract_kind', 40)->default('other');
            $table->string('entry_mode', 20);
            $table->string('status', 20)->default('draft');
            $table->string('contracting_party', 200)->nullable();
            $table->string('contracted_party', 200)->nullable();
            $table->text('object')->nullable();
            $table->longText('content')->nullable();
            $table->string('external_reference', 150)->nullable();
            $table->date('signed_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('capacity_notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->unique(['id', 'organization_id'], 'contracts_id_org_unique');
            $table->unique(['organization_id', 'code'], 'contracts_org_code_unique');
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['initiative_id', 'organization_id'], 'contracts_initiative_org_fk')->references(['id', 'organization_id'])->on('initiatives')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'contracts_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('project_contract_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contract_id');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('reason', 300);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->unique(['organization_id', 'contract_id', 'version'], 'contract_versions_unique');
            $table->foreign(['contract_id', 'organization_id'], 'contract_versions_contract_org_fk')->references(['id', 'organization_id'])->on('project_contracts')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('project_contract_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contract_id');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->string('category', 40)->default('related');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->foreign(['contract_id', 'organization_id'], 'contract_files_contract_org_fk')->references(['id', 'organization_id'])->on('project_contracts')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_contract_attachments');
        Schema::dropIfExists('project_contract_versions');
        Schema::dropIfExists('project_contracts');
    }
};
