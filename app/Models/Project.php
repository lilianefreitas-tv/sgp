<?php

namespace App\Models;

use App\Enums\ManagementLevel;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'manager_id',
        'name',
        'description',
        'objective',
        'justification',
        'management_level',
        'methodology',
        'status',
        'start_date',
        'expected_end_date',
        'end_date',
        'is_active',
        'archived_at',
    ];

    protected static function booted(): void
    {
        static::created(function (Project $project): void {
            if (blank($project->code)) {
                $project->forceFill([
                    'code' => 'PRJ-'.str_pad((string) $project->id, 4, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'management_level' => ManagementLevel::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdministrator()) {
            return $query;
        }

        return $query->whereHas('memberships', fn (Builder $membership) => $membership
            ->where('user_id', $user->id)
            ->where('is_active', true));
    }

    public function hasActiveMember(User $user): bool
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }
}
