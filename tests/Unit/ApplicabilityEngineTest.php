<?php

namespace Tests\Unit;

use App\Enums\ApplicabilityOutcome;
use App\Enums\ApplicabilityTargetType;
use App\Services\ApplicabilityEngine;
use App\ValueObjects\ApplicabilityContext;
use LogicException;
use Tests\TestCase;

class ApplicabilityEngineTest extends TestCase
{
    public function test_engine_is_deterministic_and_applies_precedence(): void
    {
        $context = new ApplicabilityContext(1, 'initiative', 1, 'draft', 9, ['origin' => 'internal', 'execution_nature' => 'internal', 'financial_management_mode' => 'not_applicable', 'management_level' => 'complete', 'methodology' => 'kanban'], ApplicabilityTargetType::Module, 'commercial.journey', now(), '1.0.0');
        $rules = [
            ['key' => 'optional', 'priority' => 1, 'conditions' => [], 'outcome' => 'optional', 'reason_code' => 'DEFAULT', 'safe_explanation' => 'Opcional.'],
            ['key' => 'not-applicable', 'priority' => 400, 'conditions' => [['field' => 'origin', 'operator' => 'equals', 'value' => 'internal']], 'outcome' => 'not_applicable', 'reason_code' => 'INTERNAL', 'safe_explanation' => 'Não aplicável.'],
        ];
        $first = app(ApplicabilityEngine::class)->evaluate($context, $rules);
        $second = app(ApplicabilityEngine::class)->evaluate($context, $rules);
        $this->assertSame(ApplicabilityOutcome::NotApplicable, $first->outcome);
        $this->assertSame($first->canonicalInputHash, $second->canonicalInputHash);
        $this->assertSame(['not-applicable'], $first->matchedRuleKeys);
    }

    public function test_engine_rejects_unknown_operator_and_priority_tie(): void
    {
        $context = new ApplicabilityContext(1, 'project', 1, 'planning', 9, ['execution_nature' => 'internal', 'financial_management_mode' => 'not_applicable', 'management_level' => 'essential', 'methodology' => 'kanban'], ApplicabilityTargetType::Action, 'project.configuration.update', now(), '1.0.0');
        $this->expectException(LogicException::class);
        app(ApplicabilityEngine::class)->evaluate($context, [['key' => 'bad', 'priority' => 1, 'conditions' => [['field' => 'origin', 'operator' => 'contains', 'value' => 'x']], 'outcome' => 'optional', 'reason_code' => 'BAD', 'safe_explanation' => '']]);
    }
}
