<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'user_id',
        'event_type',
        'subject_type',
        'subject_id',
        'description',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param array<string, mixed> $metadata */
    public static function record(
        Project $project,
        ?User $user,
        string $eventType,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
    ): self {
        return self::create([
            'project_id' => $project->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
