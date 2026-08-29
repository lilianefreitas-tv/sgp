<?php

namespace App\Models;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use Database\Factories\RequirementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requirement extends Model
{
    /** @use HasFactory<RequirementFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'responsible_id',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'acceptance_criteria',
        'source',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Requirement $requirement): void {
            if (blank($requirement->code)) {
                $lastCode = self::query()
                    ->where('project_id', $requirement->project_id)
                    ->latest('id')
                    ->value('code');

                $sequence = $lastCode
                    ? ((int) str_replace('REQ-', '', $lastCode)) + 1
                    : 1;

                $requirement->forceFill([
                    'code' => 'REQ-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => RequirementType::class,
            'priority' => RequirementPriority::class,
            'status' => RequirementStatus::class,
            'current_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RequirementVersion::class)->orderByDesc('version_number');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(ProjectTestCase::class);
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'requirement_dependencies',
            'requirement_id',
            'depends_on_requirement_id',
        )->using(RequirementDependency::class)->withTimestamps();
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
