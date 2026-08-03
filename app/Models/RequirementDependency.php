<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RequirementDependency extends Pivot
{
    protected $table = 'requirement_dependencies';

    public $incrementing = true;

    protected $fillable = [
        'organization_id',
        'requirement_id',
        'depends_on_requirement_id',
    ];

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'depends_on_requirement_id');
    }
}
