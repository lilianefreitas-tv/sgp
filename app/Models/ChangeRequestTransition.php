<?php

namespace App\Models;

use App\Enums\ChangeRequestState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ChangeRequestTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'change_request_id',
        'from_state',
        'to_state',
        'actor_id',
        'reason',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'from_state' => ChangeRequestState::class,
            'to_state' => ChangeRequestState::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('O histórico de transições é imutável.'));
        static::deleting(fn () => throw new LogicException('O histórico de transições não pode ser excluído.'));
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
