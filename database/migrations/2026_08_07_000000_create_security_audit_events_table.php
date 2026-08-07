<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id');
            $table->string('action', 100);
            $table->string('result', 20);
            $table->string('environment', 40);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['target_user_id', 'occurred_at'], 'security_audit_target_date_idx');
            $table->index(['organization_id', 'occurred_at'], 'security_audit_org_date_idx');
            $table->index(['action', 'result'], 'security_audit_action_result_idx');
            $table->index('request_id', 'security_audit_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_events');
    }
};
