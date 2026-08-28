<?php

namespace App\Models;

use App\Enums\ChangeRequestAnalysisStatus;
use App\Enums\ChangeRequestClassification;
use App\Enums\ChangeRequestRecommendation;
use App\Enums\ChangeRequestRiskLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ChangeRequestImpactAnalysis extends Model
{
    protected $fillable = [
        'organization_id',
        'change_request_id',
        'round',
        'analyst_id',
        'status',
        'classification',
        'risk_level',
        'recommendation',
        'executive_summary',
        'scope_impact',
        'requirements_impact',
        'technical_impact',
        'data_impact',
        'security_impact',
        'schedule_impact',
        'resources_impact',
        'cost_impact',
        'contract_impact',
        'quality_impact',
        'testing_impact',
        'operations_impact',
        'documentation_impact',
        'risks_and_mitigations',
        'estimated_effort_hours',
        'estimated_schedule_days',
        'estimated_cost_amount',
        'completed_by',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'status' => ChangeRequestAnalysisStatus::class,
            'classification' => ChangeRequestClassification::class,
            'risk_level' => ChangeRequestRiskLevel::class,
            'recommendation' => ChangeRequestRecommendation::class,
            'estimated_effort_hours' => 'decimal:2',
            'estimated_schedule_days' => 'integer',
            'estimated_cost_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $analysis): void {
            if ($analysis->getRawOriginal('status') === ChangeRequestAnalysisStatus::Completed->value) {
                throw new LogicException('Uma análise de impacto concluída é imutável.');
            }
        });
        static::deleting(fn () => throw new LogicException(
            'Análises de impacto não podem ser excluídas.'
        ));
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
