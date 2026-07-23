<?php

use App\Enums\ClientType;
use App\Enums\ManagementLevel;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('type', 30)->default(ClientType::Unit->value);
            $table->string('document', 30)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('contact_name', 180)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->text('objective');
            $table->text('justification')->nullable();
            $table->string('management_level', 30)->default(ManagementLevel::Simplified->value);
            $table->string('methodology', 80)->nullable();
            $table->string('status', 30)->default(ProjectStatus::Planning->value);
            $table->date('start_date')->nullable();
            $table->date('expected_end_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_active']);
            $table->index('archived_at');
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 40)->default(ProjectRole::Observer->value);
            $table->boolean('is_active')->default(true);
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'user_id', 'role']);
            $table->index(['project_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');
    }
};
