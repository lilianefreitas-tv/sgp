<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectOriginBaseline extends Model
{
    protected $fillable = ['project_id', 'established_by', 'code', 'purpose', 'checksum', 'established_at'];

    protected function casts(): array
    {
        return ['established_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function establishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'established_by');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(ProjectAttachment::class, 'project_origin_baseline_items', 'baseline_id', 'project_attachment_id')
            ->withPivot('organization_id')
            ->withTimestamps();
    }
}
