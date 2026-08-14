<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectContractAttachment extends Model
{
    protected $fillable = ['organization_id', 'contract_id', 'original_name', 'stored_name', 'disk', 'path', 'mime_type', 'size', 'checksum', 'category', 'created_by'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ProjectContract::class, 'contract_id');
    }
}
