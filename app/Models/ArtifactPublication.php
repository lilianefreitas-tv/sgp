<?php

namespace App\Models;

use App\Enums\ArtifactPublicationAudience;
use App\Enums\ArtifactPublicationMode;
use App\Enums\ArtifactPublicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ArtifactPublication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ArtifactPublicationStatus::class,
            'mode' => ArtifactPublicationMode::class,
            'audience' => ArtifactPublicationAudience::class,
            'selection' => 'array',
            'manifest' => 'array',
            'published_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Publicações documentais são permanentes e não podem ser excluídas.'));
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ArtifactRevision::class, 'artifact_revision_id');
    }

    public function workflowRound(): BelongsTo
    {
        return $this->belongsTo(ArtifactWorkflowRound::class);
    }

    public function referenceRevision(): BelongsTo
    {
        return $this->belongsTo(ArtifactRevision::class, 'reference_revision_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
