<?php

namespace App\Models;

use App\Enums\ChangeRequestBaselineDisposition;
use App\Enums\ChangeRequestContractDisposition;
use App\Enums\ChangeRequestImplementationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ChangeRequestImplementation extends Model
{
    protected $fillable = [
        'organization_id',
        'project_id',
        'change_request_id',
        'responsible_id',
        'status',
        'plan_summary',
        'execution_summary',
        'verification_summary',
        'planned_start_date',
        'target_completion_date',
        'started_at',
        'completed_at',
        'completed_by',
        'contract_disposition',
        'contract_id',
        'contract_justification',
        'amendment_reference',
        'amendment_summary',
        'amendment_effective_date',
        'amendment_contract_version',
        'baseline_disposition',
        'baseline_title',
        'baseline_justification',
        'new_baseline_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChangeRequestImplementationStatus::class,
            'contract_disposition' => ChangeRequestContractDisposition::class,
            'baseline_disposition' => ChangeRequestBaselineDisposition::class,
            'planned_start_date' => 'date',
            'target_completion_date' => 'date',
            'amendment_effective_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'amendment_contract_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $implementation): void {
            if ($implementation->getOriginal('status') === ChangeRequestImplementationStatus::Completed->value) {
                throw new LogicException('Uma implementação concluída é imutável.');
            }
        });
        static::deleting(fn () => throw new LogicException('Registros de implementação não podem ser excluídos.'));
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ProjectContract::class, 'contract_id');
    }

    public function newBaseline(): BelongsTo
    {
        return $this->belongsTo(ProjectBaseline::class, 'new_baseline_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ChangeRequestImplementationEvent::class, 'implementation_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }
}
