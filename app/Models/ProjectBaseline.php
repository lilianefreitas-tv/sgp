<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ProjectBaseline extends Model
{
    protected $fillable = ['organization_id', 'project_id', 'version', 'title', 'justification', 'established_at', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'established_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Baselines não podem ser excluídas.'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectBaselineItem::class);
    }
}
