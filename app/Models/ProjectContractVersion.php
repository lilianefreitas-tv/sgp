<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectContractVersion extends Model
{
    protected $fillable = ['organization_id', 'contract_id', 'version', 'snapshot', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ProjectContract::class, 'contract_id');
    }
}
