<?php

namespace App\Services;

use App\Enums\ApplicabilityOutcome;
use App\ValueObjects\ApplicabilityContext;
use App\ValueObjects\ApplicabilityResult;
use Illuminate\Support\Str;
use LogicException;

class ApplicabilityEngine
{
    public function __construct(private ApplicabilityRuleValidator $validator) {}

    /** @param iterable<array<string, mixed>|object> $rules */
    public function evaluate(ApplicabilityContext $context, iterable $rules): ApplicabilityResult
    {
        $matches = [];
        foreach ($rules as $rule) {
            $data = is_array($rule) ? $rule : $rule->toArray();
            $conditions = $data['conditions'] ?? [];
            $this->validator->validate($conditions);
            if (($data['status'] ?? 'active') !== 'active' || ! $this->matches($context, $conditions)) continue;
            $matches[] = $data;
        }
        usort($matches, fn (array $a, array $b) => ((int) $b['priority']) <=> ((int) $a['priority']));
        if (isset($matches[1]) && $matches[0]['priority'] === $matches[1]['priority']) throw new LogicException('Empate de prioridade em regras de aplicabilidade.');
        $match = $matches[0] ?? ['key' => 'default.optional', 'outcome' => ApplicabilityOutcome::Optional->value,
            'reason_code' => 'DEFAULT_OPTIONAL', 'safe_explanation' => 'Aplicável conforme a configuração atual.'];
        $outcome = $match['outcome'] instanceof ApplicabilityOutcome ? $match['outcome'] : ApplicabilityOutcome::from($match['outcome']);
        $input = $context->canonicalInput(); ksort($input); $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        return new ApplicabilityResult($outcome, $match['reason_code'], $match['safe_explanation'], $context->platformRuleSetVersion,
            $context->configurationVersionId, $context->evaluatedAt, array_values(array_filter([$match['key'] ?? null])), $hash);
    }

    /** @param array<int, array<string, mixed>> $conditions */
    private function matches(ApplicabilityContext $context, array $conditions): bool
    {
        $input = $context->dimensions + ['subject_state' => $context->subjectState, 'target_type' => $context->targetType->value, 'target_key' => $context->targetKey];
        foreach ($conditions as $condition) {
            $actual = $input[$condition['field']]; $expected = $condition['value'];
            $matched = match ($condition['operator']) {
                'equals' => $actual === $expected, 'not_equals' => $actual !== $expected,
                'in' => in_array($actual, $expected, true), 'not_in' => ! in_array($actual, $expected, true),
            };
            if (! $matched) return false;
        }
        return true;
    }
}
