<?php

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('execution_nature', 30)
                ->default(ExecutionNature::Internal->value)
                ->after('justification');
            $table->string('financial_management_mode', 30)
                ->default(FinancialManagementMode::NotApplicable->value)
                ->after('execution_nature');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('projects')->whereNull('client_id')->exists()) {
            throw new LogicException(
                'O rollback da F6 exige vincular um demandante aos projetos que atualmente não possuem client_id.'
            );
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable(false)->change();
            $table->dropColumn([
                'execution_nature',
                'financial_management_mode',
            ]);
        });
    }
};
