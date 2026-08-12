<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_applicability_rule_sets', function (Blueprint $table): void {
            $table->id(); $table->string('code', 80); $table->string('version', 30); $table->string('status', 20);
            $table->timestamp('effective_from'); $table->timestamp('activated_at')->nullable(); $table->timestamp('retired_at')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1); $table->string('checksum', 64); $table->timestamps();
            $table->unique(['code', 'version'], 'applicability_rule_sets_code_version_unique');
        });
        Schema::create('platform_applicability_rules', function (Blueprint $table): void {
            $table->id(); $table->foreignId('rule_set_id')->constrained('platform_applicability_rule_sets')->restrictOnDelete();
            $table->string('key', 100); $table->unsignedSmallInteger('priority'); $table->string('target_type', 20); $table->string('target_key', 100);
            $table->json('conditions'); $table->string('outcome', 30); $table->string('reason_code', 80); $table->string('safe_explanation', 255); $table->timestamps();
            $table->unique(['rule_set_id', 'key'], 'applicability_rules_set_key_unique');
        });
        Schema::create('applicability_decisions', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organization_id'); $table->unsignedBigInteger('initiative_id')->nullable(); $table->unsignedBigInteger('project_id')->nullable();
            $table->string('target_type', 20); $table->string('target_key', 100); $table->foreignId('rule_set_id')->constrained('platform_applicability_rule_sets')->restrictOnDelete();
            $table->unsignedBigInteger('initiative_configuration_version_id')->nullable(); $table->unsignedBigInteger('project_configuration_version_id')->nullable();
            $table->timestamp('evaluated_at'); $table->string('outcome', 30); $table->string('reason_code', 80); $table->json('dimensions_snapshot'); $table->string('explanation_snapshot', 255); $table->string('canonical_input_hash', 64); $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete(); $table->uuid('request_id'); $table->timestamps();
            $table->foreign('organization_id', 'applicability_decisions_org_fk')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['initiative_id', 'organization_id'], 'applicability_decisions_initiative_org_fk')->references(['id', 'organization_id'])->on('initiatives')->restrictOnDelete();
            $table->foreign(['project_id', 'organization_id'], 'applicability_decisions_project_org_fk')->references(['id', 'organization_id'])->on('projects')->restrictOnDelete();
            $table->foreign(['initiative_configuration_version_id', 'organization_id'], 'applicability_decisions_initiative_version_org_fk')->references(['id', 'organization_id'])->on('initiative_configuration_versions')->restrictOnDelete();
            $table->foreign(['project_configuration_version_id', 'organization_id'], 'applicability_decisions_project_version_org_fk')->references(['id', 'organization_id'])->on('project_configuration_versions')->restrictOnDelete();
            $table->index(['organization_id', 'project_id'], 'applicability_decisions_project_org_idx'); $table->index(['organization_id', 'initiative_id'], 'applicability_decisions_initiative_org_idx');
        });
        $now = now();
        $setId = DB::table('platform_applicability_rule_sets')->insertGetId(['code' => 'platform-default', 'version' => '1.0.0', 'status' => 'active', 'effective_from' => $now, 'activated_at' => $now, 'schema_version' => 1, 'checksum' => hash('sha256', 'platform-default:1.0.0'), 'created_at' => $now, 'updated_at' => $now]);
        foreach ([
            ['internal-commercial-na', 400, 'module', 'commercial.journey', [['field' => 'origin', 'operator' => 'equals', 'value' => 'internal']], 'not_applicable', 'INTERNAL_NO_COMMERCIAL', 'A jornada comercial não se aplica à origem interna.'],
            ['direct-commercial-na', 400, 'module', 'commercial.journey', [['field' => 'origin', 'operator' => 'equals', 'value' => 'direct']], 'not_applicable', 'DIRECT_NO_COMMERCIAL', 'A jornada comercial não se aplica à origem direta.'],
            ['commercial-journey', 200, 'module', 'commercial.journey', [['field' => 'origin', 'operator' => 'equals', 'value' => 'commercial']], 'optional', 'COMMERCIAL_JOURNEY', 'A jornada comercial é aplicável.'],
            ['complete-baseline-required', 350, 'module', 'governance.baseline', [['field' => 'management_level', 'operator' => 'equals', 'value' => 'complete']], 'required', 'COMPLETE_GOVERNANCE', 'O controle formal é obrigatório para governança completa.'],
            ['closed-update-unavailable', 500, 'action', 'project.configuration.update', [['field' => 'subject_state', 'operator' => 'in', 'value' => ['completed', 'cancelled']]], 'unavailable', 'STATE_UNAVAILABLE', 'A ação é indisponível no estado atual.'],
        ] as [$key, $priority, $type, $target, $conditions, $outcome, $reason, $explanation]) DB::table('platform_applicability_rules')->insert(['rule_set_id' => $setId, 'key' => $key, 'priority' => $priority, 'target_type' => $type, 'target_key' => $target, 'conditions' => json_encode($conditions), 'outcome' => $outcome, 'reason_code' => $reason, 'safe_explanation' => $explanation, 'created_at' => $now, 'updated_at' => $now]);
    }
    public function down(): void { Schema::dropIfExists('applicability_decisions'); Schema::dropIfExists('platform_applicability_rules'); Schema::dropIfExists('platform_applicability_rule_sets'); }
};
