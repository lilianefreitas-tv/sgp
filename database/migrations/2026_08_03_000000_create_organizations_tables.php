<?php

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('slug', 120)->unique();
            $table->string('type', 30)->nullable();
            $table->string('status', 30)->default(OrganizationStatus::Active->value);
            $table->string('timezone', 60)->default('America/Belem');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['status', 'name']);
        });

        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role_code', 30);
            $table->string('status', 30)->default(OrganizationMembershipStatus::Active->value);
            $table->boolean('is_default')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');
    }
};
