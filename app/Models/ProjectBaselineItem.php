<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProjectBaselineItem extends Model
{
    protected $fillable = ['organization_id', 'project_baseline_id', 'item_type', 'source_id', 'source_version', 'code', 'title', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Itens de baseline são imutáveis.'));
        static::deleting(fn () => throw new LogicException('Itens de baseline não podem ser excluídos.'));
    }

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(ProjectBaseline::class, 'project_baseline_id');
    }
}
