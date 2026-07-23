<?php

use App\Enums\GlobalProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('global_profile', 30)
                ->default(GlobalProfile::User->value)
                ->after('password');
            $table->boolean('is_active')->default(true)->after('global_profile');
        });

        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId !== null) {
            DB::table('users')
                ->where('id', $firstUserId)
                ->update(['global_profile' => GlobalProfile::Administrator->value]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['global_profile', 'is_active']);
        });
    }
};
