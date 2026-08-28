<?php

namespace App\Models;

use App\Enums\ContractEntryMode;
use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectContract extends Model
{
    protected $fillable = ['organization_id', 'initiative_id', 'project_id', 'code', 'title', 'contract_kind', 'entry_mode', 'status',
        'contracting_party', 'contracted_party', 'object', 'content', 'external_reference', 'signed_at',
        'start_date', 'end_date', 'amount', 'capacity_notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['entry_mode' => ContractEntryMode::class, 'status' => ContractStatus::class,
            'signed_at' => 'date', 'start_date' => 'date', 'end_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProjectContractVersion::class, 'contract_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectContractAttachment::class, 'contract_id');
    }
}
