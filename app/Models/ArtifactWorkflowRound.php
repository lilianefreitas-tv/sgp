<?php

namespace App\Models;

use App\Enums\ArtifactWorkflowState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ArtifactWorkflowRound extends Model
{
    private static bool $writingThroughService = false;

    protected $fillable = ['organization_id',
        'artifact_id', 'artifact_revision_id', 'sequence', 'state', 'submitted_by', 'submitted_at', 'closed_at', 'source_initiative_configuration_version_id', 'source_project_configuration_version_id', 'applicability_outcome', 'applicability_reason_code'];

    protected function casts(): array
    {
        return ['state' => ArtifactWorkflowState::class, 'submitted_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn () => self::$writingThroughService ?: throw new LogicException('Workflow rounds must be created through the workflow service.'));
        static::updating(fn () => self::$writingThroughService ?: throw new LogicException('Workflow rounds are immutable outside the workflow service.'));
        static::deleting(fn () => throw new LogicException('Workflow rounds are immutable.'));
    }

    public static function createThroughService(array $attributes): self
    {
        return self::write(fn () => self::query()->create($attributes));
    }

    public static function transition(self $round, ArtifactWorkflowState $state, bool $close = true): void
    {
        self::write(function () use ($round, $state, $close): void {
            $round->forceFill(['state' => $state, 'closed_at' => $close ? now() : null])->save();
        });
    }

    private static function write(callable $callback): mixed
    {
        self::$writingThroughService = true;
        try {
            return $callback();
        } finally {
            self::$writingThroughService = false;
        }
    }

    public function saveQuietly(array $options = []): bool
    {
        if (! self::$writingThroughService) {
            throw new LogicException('Workflow rounds are immutable outside the workflow service.');
        }

        return parent::saveQuietly($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Workflow rounds are immutable.');
    }

    public function forceDelete(): bool
    {
        throw new LogicException('Workflow rounds are immutable.');
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ArtifactRevision::class, 'artifact_revision_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function sourceInitiativeConfigurationVersion(): BelongsTo
    {
        return $this->belongsTo(InitiativeConfigurationVersion::class);
    }

    public function sourceProjectConfigurationVersion(): BelongsTo
    {
        return $this->belongsTo(ProjectConfigurationVersion::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ArtifactWorkflowDecision::class, 'round_id');
    }
}
