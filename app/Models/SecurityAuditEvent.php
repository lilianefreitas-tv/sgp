<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'actor_id',
        'target_user_id',
        'request_id',
        'action',
        'result',
        'environment',
        'ip_address',
        'user_agent',
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
}
