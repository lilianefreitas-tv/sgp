<?php

namespace App\Models;

use App\Enums\ApplicabilityOutcome;
use App\Enums\ApplicabilityTargetType;
use App\Models\Concerns\ImmutableConfigurationVersion;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class ApplicabilityDecision extends Model
{
    use ImmutableConfigurationVersion;
    protected $guarded = [];
    protected function casts(): array { return ['target_type' => ApplicabilityTargetType::class, 'outcome' => ApplicabilityOutcome::class, 'evaluated_at' => 'datetime', 'dimensions_snapshot' => 'array']; }
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (($model->initiative_id === null) === ($model->project_id === null)) throw new LogicException('A decisão deve referenciar exatamente uma iniciativa ou projeto.');
        });
    }
}
