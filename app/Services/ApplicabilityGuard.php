<?php

namespace App\Services;

use App\Enums\ApplicabilityOutcome;
use App\Models\ApplicabilityDecision;
use App\Models\PlatformApplicabilityRuleSet;
use App\Models\User;
use App\ValueObjects\ApplicabilityContext;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApplicabilityGuard
{
    public function __construct(private ApplicabilityEngine $engine) {}
    public function assertAllowed(ApplicabilityContext $context, User $actor, bool $material = false): void
    {
        $set = PlatformApplicabilityRuleSet::query()->where('status', 'active')->whereNull('retired_at')->firstOrFail();
        $result = $this->engine->evaluate($context, $set->rules()->get());
        if ($material) $this->record($context, $result, $set, $actor);
        if (in_array($result->outcome, [ApplicabilityOutcome::NotApplicable, ApplicabilityOutcome::Unavailable], true)) {
            throw new HttpResponseException(response()->json(['message' => $result->safeExplanation, 'reason_code' => $result->reasonCode], 409));
        }
    }
    private function record(ApplicabilityContext $context, $result, PlatformApplicabilityRuleSet $set, User $actor): void
    {
        ApplicabilityDecision::create(['organization_id' => $context->organizationId, $context->subjectType.'_id' => $context->subjectId,
            'target_type' => $context->targetType, 'target_key' => $context->targetKey, 'rule_set_id' => $set->id,
            $context->subjectType.'_configuration_version_id' => $context->configurationVersionId, 'evaluated_at' => $context->evaluatedAt,
            'outcome' => $result->outcome, 'reason_code' => $result->reasonCode, 'dimensions_snapshot' => $context->dimensions,
            'explanation_snapshot' => $result->safeExplanation, 'canonical_input_hash' => $result->canonicalInputHash,
            'requested_by' => $actor->id, 'request_id' => request()?->header('X-Request-Id') ?? (string) str()->uuid()]);
    }
}
