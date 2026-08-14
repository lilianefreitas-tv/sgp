<?php

namespace App\Models;

use App\Enums\ChangeRequestOrigin;
use App\Enums\ChangeRequestState;
use App\Enums\ChangeRequestUrgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ChangeRequest extends Model
{
    protected $fillable = [
        'organization_id',
        'project_id',
        'sequence',
        'code',
        'origin',
        'title',
        'description',
        'justification',
        'urgency',
        'baseline_id',
        'requester_id',
        'analyst_id',
        'state',
        'submitted_at',
        'analysis_started_at',
        'returned_at',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'origin' => ChangeRequestOrigin::class,
            'urgency' => ChangeRequestUrgency::class,
            'state' => ChangeRequestState::class,
            'submitted_at' => 'datetime',
            'analysis_started_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException(
            'Solicitações de mudança não podem ser excluídas. Cancele a solicitação para preservar o histórico.'
        ));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(ProjectBaseline::class, 'baseline_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function affectedItems(): HasMany
    {
        return $this->hasMany(ChangeRequestAffectedItem::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(ChangeRequestTransition::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class, 'context_id')
            ->where('context_type', 'change_request');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query) => $query
            ->where(function (Builder $query) use ($search): void {
                $query->whereLike('code', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('title', "%{$search}%", caseSensitive: false);
            }));
    }
}
