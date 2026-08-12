<?php

namespace App\Models;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\InitiativeState;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Initiative extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Initiatives cannot be deleted; archive them instead.'));
    }

    protected $fillable = ['code', 'title', 'context', 'origin', 'state', 'execution_nature',
        'financial_management_mode', 'management_level', 'methodology', 'lock_version',
        'created_by', 'converted_at', 'converted_by', 'archived_at'];

    protected function casts(): array
    {
        return ['origin' => InitiativeOrigin::class, 'state' => InitiativeState::class,
            'execution_nature' => ExecutionNature::class, 'financial_management_mode' => FinancialManagementMode::class,
            'management_level' => ManagementLevel::class, 'methodology' => ProjectMethodology::class,
            'lock_version' => 'integer', 'converted_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function configurationVersions(): HasMany
    {
        return $this->hasMany(InitiativeConfigurationVersion::class);
    }

    public function forceDelete(): bool
    {
        throw new LogicException('Initiatives cannot be deleted; archive them instead.');
    }
}
