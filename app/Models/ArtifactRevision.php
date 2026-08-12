<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ArtifactRevision extends Model
{
    use HasFactory;

    private static bool $creatingThroughService = false;

    protected $fillable = [
        'artifact_id', 'sequence', 'schema_version', 'content', 'metadata',
        'source_initiative_configuration_version_id', 'source_project_configuration_version_id',
        'checksum', 'changed_by', 'change_reason', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['content' => 'array', 'metadata' => 'array', 'schema_version' => 'integer', 'recorded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (! self::$creatingThroughService) {
                throw new LogicException('Artifact revisions must be created through the revision service.');
            }
        });
        static::updating(fn () => throw new LogicException('Artifact revisions are immutable; create a new revision instead.'));
        static::deleting(fn () => throw new LogicException('Artifact revisions are immutable and cannot be deleted.'));
    }

    public function saveQuietly(array $options = []): bool
    {
        if ($this->exists || ! self::$creatingThroughService) {
            throw new LogicException('Artifact revisions are immutable; create a new revision instead.');
        }

        return parent::saveQuietly($options);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists || ! self::$creatingThroughService) {
            throw new LogicException('Artifact revisions are immutable; create a new revision instead.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Artifact revisions are immutable and cannot be deleted.');
    }

    public function forceDelete(): bool
    {
        throw new LogicException('Artifact revisions are immutable and cannot be deleted.');
    }

    /** @param array<string, mixed> $attributes */
    public static function createThroughService(array $attributes): self
    {
        self::$creatingThroughService = true;

        try {
            return self::query()->create($attributes);
        } finally {
            self::$creatingThroughService = false;
        }
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }

    public function sourceInitiativeConfigurationVersion(): BelongsTo
    {
        return $this->belongsTo(InitiativeConfigurationVersion::class);
    }

    public function sourceProjectConfigurationVersion(): BelongsTo
    {
        return $this->belongsTo(ProjectConfigurationVersion::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
