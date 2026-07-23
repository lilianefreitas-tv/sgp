<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanColumn extends Model
{
    protected $fillable = [
        'kanban_board_id',
        'status',
        'name',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'kanban_board_id');
    }

    public function taskPositions(): HasMany
    {
        return $this->hasMany(KanbanTaskPosition::class)->orderBy('position');
    }
}
