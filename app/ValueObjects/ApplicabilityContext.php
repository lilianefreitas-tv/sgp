<?php

namespace App\ValueObjects;

use App\Enums\ApplicabilityTargetType;
use DateTimeInterface;

readonly class ApplicabilityContext
{
    /** @param array<string, string> $dimensions */
    public function __construct(
        public int $organizationId, public string $subjectType, public int $subjectId, public string $subjectState,
        public int $configurationVersionId, public array $dimensions, public ApplicabilityTargetType $targetType,
        public string $targetKey, public DateTimeInterface $evaluatedAt, public string $platformRuleSetVersion,
    ) {}

    /** @return array<string, mixed> */
    public function canonicalInput(): array
    {
        return ['organization_id' => $this->organizationId, 'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId, 'subject_state' => $this->subjectState,
            'configuration_version_id' => $this->configurationVersionId, 'dimensions' => $this->dimensions,
            'target_type' => $this->targetType->value, 'target_key' => $this->targetKey,
            'evaluated_at' => $this->evaluatedAt->format(DATE_ATOM), 'rule_set_version' => $this->platformRuleSetVersion];
    }
}
