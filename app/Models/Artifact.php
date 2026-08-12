<?php

namespace App\Models;

use App\Enums\ArtifactType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Artifact extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Artifacts cannot be deleted; archive them instead.'));
    }

    protected $fillable = [
        'initiative_id', 'project_id', 'code', 'type', 'title', 'description',
        'current_revision_sequence', 'created_by', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['type' => ArtifactType::class, 'current_revision_sequence' => 'integer', 'archived_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArtifactRevision::class);
    }

    public function forceDelete(): bool
    {
        throw new LogicException('Artifacts cannot be deleted; archive them instead.');
    }
}
