<?php

namespace App\Models;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use App\Models\Concerns\ImmutableConfigurationVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InitiativeConfigurationVersion extends Model
{
    use HasFactory, ImmutableConfigurationVersion;

    protected $fillable = ['initiative_id', 'sequence', 'origin', 'execution_nature', 'financial_management_mode',
        'management_level', 'methodology', 'effective_from', 'superseded_at', 'changed_by', 'justification',
        'applicability_impact', 'recorded_at'];

    protected function casts(): array
    {
        return ['origin' => InitiativeOrigin::class, 'execution_nature' => ExecutionNature::class,
            'financial_management_mode' => FinancialManagementMode::class, 'management_level' => ManagementLevel::class,
            'methodology' => ProjectMethodology::class, 'effective_from' => 'datetime', 'superseded_at' => 'datetime',
            'recorded_at' => 'datetime', 'applicability_impact' => 'array'];
    }

    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
