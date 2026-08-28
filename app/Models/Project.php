<?php

namespace App\Models;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use App\Enums\ProjectOriginType;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'manager_id',
        'initiative_id',
        'origin_type',
        'source_initiative_configuration_version_id',
        'name',
        'description',
        'objective',
        'justification',
        'execution_nature',
        'financial_management_mode',
        'document_context',
        'problem_statement',
        'solution_summary',
        'target_audience',
        'scope_included',
        'scope_excluded',
        'assumptions',
        'constraints',
        'success_criteria',
        'future_vision',
        'management_level',
        'methodology',
        'status',
        'start_date',
        'expected_end_date',
        'end_date',
        'is_active',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'execution_nature' => ExecutionNature::class,
            'financial_management_mode' => FinancialManagementMode::class,
            'management_level' => ManagementLevel::class,
            'methodology' => ProjectMethodology::class,
            'origin_type' => ProjectOriginType::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function methodologyLabel(): string
    {
        if (blank($this->methodology)) {
            return 'Não informada';
        }

        return $this->methodology instanceof ProjectMethodology
            ? $this->methodology->label()
            : (ProjectMethodology::tryFrom((string) $this->methodology)?->label() ?? (string) $this->methodology);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function kanbanBoard(): HasOne
    {
        return $this->hasOne(KanbanBoard::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    public function originDocumentVersions(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class)->where('is_origin_document', true);
    }

    public function originBaseline(): HasOne
    {
        return $this->hasOne(ProjectOriginBaseline::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class);
    }

    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    public function configurationVersions(): HasMany
    {
        return $this->hasMany(ProjectConfigurationVersion::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(ProjectContract::class);
    }

    public function baselines(): HasMany
    {
        return $this->hasMany(ProjectBaseline::class)->orderByDesc('version');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class)->orderByDesc('updated_at');
    }

    public function originDocuments(): HasMany
    {
        return $this->hasMany(Artifact::class, 'initiative_id', 'initiative_id');
    }

    public function documentRoleAssignments(): HasMany
    {
        return $this->hasMany(DocumentRoleAssignment::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->administersCurrentOrganization()) {
            return $query;
        }

        return $query->whereHas('memberships', fn (Builder $membership) => $membership
            ->where('user_id', $user->id)
            ->where('is_active', true));
    }

    public function hasActiveMember(User $user): bool
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }
}
