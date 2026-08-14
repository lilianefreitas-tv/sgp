<?php

namespace App\Models;

use App\Enums\ArtifactWorkflowDecisionType;
use App\Enums\DocumentRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ArtifactWorkflowDecision extends Model
{
    private static bool $creatingThroughService = false;

    protected $fillable = ['organization_id',
        'round_id', 'artifact_revision_id', 'actor_id', 'role', 'decision', 'justification', 'metadata', 'decided_at'];

    protected function casts(): array
    {
        return ['role' => DocumentRole::class, 'decision' => ArtifactWorkflowDecisionType::class, 'metadata' => 'array', 'decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn () => self::$creatingThroughService ?: throw new LogicException('Decisions must be recorded through the workflow service.'));
        static::updating(fn () => throw new LogicException('Workflow decisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Workflow decisions are immutable.'));
    }

    public static function createThroughService(array $attributes): self
    {
        self::$creatingThroughService = true;
        try {
            return self::query()->create($attributes);
        } finally {
            self::$creatingThroughService = false;
        }
    }

    public function save(array $options = []): bool
    {
        if ($this->exists || ! self::$creatingThroughService) {
            throw new LogicException('Workflow decisions are immutable.');
        }

        return parent::save($options);
    }

    public function saveQuietly(array $options = []): bool
    {
        return $this->save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Workflow decisions are immutable.');
    }

    public function forceDelete(): bool
    {
        throw new LogicException('Workflow decisions are immutable.');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(ArtifactWorkflowRound::class, 'round_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ArtifactRevision::class, 'artifact_revision_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
