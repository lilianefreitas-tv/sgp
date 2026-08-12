<?php

namespace App\Models;

use App\Enums\ApplicabilityOutcome;
use App\Enums\ApplicabilityTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PlatformApplicabilityRule extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['conditions' => 'array', 'outcome' => ApplicabilityOutcome::class, 'target_type' => ApplicabilityTargetType::class]; }
    protected static function booted(): void
    {
        static::saving(function (self $rule): void { app(\App\Services\ApplicabilityRuleValidator::class)->validate($rule->conditions ?? []); });
        static::updating(function (self $rule): void { if ($rule->ruleSet?->status === 'active') throw new LogicException('Regras de conjunto ativo não podem ser editadas.'); });
    }
    public function ruleSet(): BelongsTo { return $this->belongsTo(PlatformApplicabilityRuleSet::class, 'rule_set_id'); }
}
