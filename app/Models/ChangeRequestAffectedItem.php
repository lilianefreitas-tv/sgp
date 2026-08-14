<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequestAffectedItem extends Model
{
    protected $fillable = [
        'organization_id',
        'change_request_id',
        'item_type',
        'source_id',
        'code',
        'title',
    ];

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }
}
