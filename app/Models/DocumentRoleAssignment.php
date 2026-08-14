<?php

namespace App\Models;

use App\Enums\DocumentRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DocumentRoleAssignment extends Model
{
    private static bool $writingThroughService = false;

    protected $fillable = ['organization_id', 'initiative_id', 'project_id', 'user_id', 'role', 'effective_from', 'effective_until', 'assigned_by'];

    protected function casts(): array
    {
        return ['role' => DocumentRole::class, 'effective_from' => 'datetime', 'effective_until' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn () => self::$writingThroughService ?: throw new LogicException('Document roles must be assigned through the workflow service.'));
        static::updating(function (self $model): void {
            if (! self::$writingThroughService || ! $model->isDirty('effective_until') || count($model->getDirty()) !== 1) {
                throw new LogicException('Document role history is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Document role history is immutable.'));
    }

    public static function assignThroughService(array $attributes): self
    {
        return self::write(fn () => self::query()->create($attributes));
    }

    public static function closeThroughService(self $assignment): void
    {
        self::write(fn () => $assignment->forceFill(['effective_until' => now()])->save());
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
            throw new LogicException('Document role history is immutable.');
        }

        return parent::saveQuietly($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Document role history is immutable.');
    }

    public function forceDelete(): bool
    {
        throw new LogicException('Document role history is immutable.');
    }

    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
