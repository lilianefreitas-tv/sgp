<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ChangeRequestImplementationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'change_request_id',
        'implementation_id',
        'event_type',
        'actor_id',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('O histórico da implementação é imutável.'));
        static::deleting(fn () => throw new LogicException('O histórico da implementação não pode ser excluído.'));
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function implementation(): BelongsTo
    {
        return $this->belongsTo(ChangeRequestImplementation::class, 'implementation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
