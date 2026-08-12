<?php

namespace App\ValueObjects;

use App\Enums\ApplicabilityOutcome;
use DateTimeInterface;

readonly class ApplicabilityResult
{
    /** @param list<string> $matchedRuleKeys */
    public function __construct(
        public ApplicabilityOutcome $outcome, public string $reasonCode, public string $safeExplanation,
        public string $ruleSetVersion, public int $configurationVersionId, public DateTimeInterface $evaluatedAt,
        public array $matchedRuleKeys, public string $canonicalInputHash,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['outcome' => $this->outcome->value, 'reason_code' => $this->reasonCode,
            'safe_explanation' => $this->safeExplanation, 'rule_set_version' => $this->ruleSetVersion,
            'configuration_version_id' => $this->configurationVersionId,
            'evaluated_at' => $this->evaluatedAt->format(DATE_ATOM), 'matched_rule_keys' => $this->matchedRuleKeys,
            'canonical_input_hash' => $this->canonicalInputHash];
    }
}
