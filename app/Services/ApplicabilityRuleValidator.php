<?php

namespace App\Services;

use App\Enums\ApplicabilityTargetType;
use LogicException;

class ApplicabilityRuleValidator
{
    private const FIELDS = ['origin', 'execution_nature', 'financial_management_mode', 'management_level', 'methodology', 'subject_state', 'target_type', 'target_key'];
    private const OPERATORS = ['equals', 'not_equals', 'in', 'not_in'];
    public function validate(array $conditions): void
    {
        if (count($conditions) > 16) throw new LogicException('A regra excede o limite de condições.');
        foreach ($conditions as $condition) {
            if (! is_array($condition) || array_diff(array_keys($condition), ['field', 'operator', 'value']) !== []
                || ! in_array($condition['field'] ?? null, self::FIELDS, true)
                || ! in_array($condition['operator'] ?? null, self::OPERATORS, true)) throw new LogicException('Condição declarativa inválida.');
            $list = in_array($condition['operator'], ['in', 'not_in'], true);
            if ($list !== is_array($condition['value'] ?? null) || ($list && $condition['value'] === [])) throw new LogicException('Valor de condição inválido.');
        }
    }
}
