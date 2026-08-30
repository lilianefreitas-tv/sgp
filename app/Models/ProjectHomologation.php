<?php

namespace App\Models;

use App\Enums\HomologationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProjectHomologation extends Model
{
    protected $fillable = [
        'organization_id', 'project_id', 'sequence', 'code', 'title', 'status',
        'baseline_id', 'commit_reference', 'environment', 'scope', 'decision_notes',
        'summary', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['status' => HomologationStatus::class, 'summary' => 'array', 'decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Decisões de homologação são imutáveis. Registre uma nova decisão.'));
        static::deleting(fn () => throw new LogicException('Decisões de homologação não podem ser removidas.'));
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function baseline(): BelongsTo { return $this->belongsTo(ProjectBaseline::class); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
