<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->timestamp('temporary_password_issued_at')->nullable()->after('must_change_password');
            $table->timestamp('password_changed_at')->nullable()->after('temporary_password_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'must_change_password',
                'temporary_password_issued_at',
                'password_changed_at',
            ]);
        });
    }
};
