<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_attachments', function (Blueprint $table): void {
            $table->string('sha256', 64)->nullable()->after('size_bytes');
        });

        Schema::table('project_documents', function (Blueprint $table): void {
            $table->string('disk', 40)->nullable()->after('version');
            $table->string('docx_sha256', 64)->nullable()->after('docx_file_name');
            $table->string('pdf_sha256', 64)->nullable()->after('pdf_file_name');
        });

        Schema::create('organization_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id')->nullable();
            $table->string('action', 100);
            $table->string('resource_type', 80)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('result', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['organization_id', 'occurred_at'], 'org_audit_org_date_idx');
            $table->index(['organization_id', 'action'], 'org_audit_org_action_idx');
            $table->index(['resource_type', 'resource_id'], 'org_audit_resource_idx');
            $table->index('request_id', 'org_audit_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_audit_events');

        Schema::table('project_documents', function (Blueprint $table): void {
            $table->dropColumn(['disk', 'docx_sha256', 'pdf_sha256']);
        });

        Schema::table('project_attachments', function (Blueprint $table): void {
            $table->dropColumn('sha256');
        });
    }
};
