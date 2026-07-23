<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'requirement_id',
        'responsible_id',
        'parent_task_id',
        'title',
        'description',
        'priority',
        'status',
        'estimated_hours',
        'start_date',
        'due_date',
        'completed_at',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            if (blank($task->code)) {
                $lastCode = self::query()
                    ->where('project_id', $task->project_id)
                    ->latest('id')
                    ->value('code');

                $sequence = $lastCode
                    ? ((int) str_replace('TAR-', '', $lastCode)) + 1
                    : 1;

                $task->forceFill([
                    'code' => 'TAR-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                ]);
            }
        });

        static::saving(function (Task $task): void {
            if ($task->status === TaskStatus::Completed && blank($task->completed_at)) {
                $task->completed_at = now();
            }

            if ($task->isDirty('status') && $task->status !== TaskStatus::Completed) {
                $task->completed_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'estimated_hours' => 'decimal:2',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public static function durationToDecimalHours(?string $duration): ?string
    {
        if (blank($duration)) {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $duration, 2));

        return number_format($hours + ($minutes / 60), 2, '.', '');
    }

    public function estimatedDuration(): ?string
    {
        if ($this->estimated_hours === null) {
            return null;
        }

        $totalMinutes = (int) round((float) $this->estimated_hours * 60);
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TaskHistory::class)->latest();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query) => $query
            ->where(function (Builder $query) use ($search): void {
                $query->whereLike('code', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('title', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('description', "%{$search}%", caseSensitive: false);
            }));
    }
}
