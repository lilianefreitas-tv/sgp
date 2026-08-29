<?php

namespace App\Models;

use App\Enums\GlobalProfile;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Services\OrganizationContext;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'global_profile',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'global_profile' => GlobalProfile::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->global_profile === GlobalProfile::Administrator;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isAdministrator();
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot(['role_code', 'status', 'is_default', 'joined_at'])
            ->withTimestamps();
    }

    public function assignedRequirements(): HasMany
    {
        return $this->hasMany(Requirement::class, 'responsible_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'responsible_id');
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(ProjectDocument::class, 'generated_by');
    }

    public function projectComments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class, 'uploaded_by');
    }

    public function testExecutions(): HasMany
    {
        return $this->hasMany(TestExecution::class, 'executed_by');
    }

    public function homologationDecisions(): HasMany
    {
        return $this->hasMany(ProjectHomologation::class, 'decided_by');
    }

    public function canPlanTests(Project $project): bool
    {
        return $this->hasProjectRole(ProjectRole::ProjectManager, $project)
            || $this->hasProjectRole(ProjectRole::Tester, $project);
    }

    public function canExecuteTests(Project $project): bool
    {
        return $this->hasProjectRole(ProjectRole::Tester, $project);
    }

    public function canHomologateProject(Project $project): bool
    {
        return $this->hasProjectRole(ProjectRole::Validator, $project);
    }

    public function hasProjectRole(ProjectRole $role, ?Project $project = null): bool
    {
        if ($this->currentOrganizationRole() === OrganizationRole::Reader) {
            return false;
        }

        return $this->projectMemberships()
            ->where('role', $role->value)
            ->where('is_active', true)
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
            ->exists();
    }

    public function canCreateProjects(): bool
    {
        $role = $this->currentOrganizationRole();

        if ($role === OrganizationRole::Reader) {
            return false;
        }

        return $this->isAdministrator()
            || in_array($role, [OrganizationRole::Owner, OrganizationRole::Administrator], true)
            || $this->hasProjectRole(ProjectRole::ProjectManager);
    }

    public function administersCurrentOrganization(): bool
    {
        return $this->isSuperAdmin()
            || in_array($this->currentOrganizationRole(), [
                OrganizationRole::Owner,
                OrganizationRole::Administrator,
            ], true);
    }

    public function canAccessProject(Project $project): bool
    {
        return $this->administersCurrentOrganization()
            || $project->hasActiveMember($this);
    }

    public function canManageProject(Project $project): bool
    {
        return $this->administersCurrentOrganization()
            || $this->hasProjectRole(ProjectRole::ProjectManager, $project);
    }

    public function canContributeToProject(Project $project): bool
    {
        if ($this->currentOrganizationRole() === OrganizationRole::Reader
            || ! $this->canAccessProject($project)) {
            return false;
        }

        $activeRoles = $this->projectMemberships()
            ->where('project_id', $project->id)
            ->where('is_active', true)
            ->pluck('role');

        return $activeRoles->isEmpty()
            ? $this->administersCurrentOrganization()
            : $activeRoles->contains(
                fn (mixed $role): bool => ($role instanceof ProjectRole ? $role->value : (string) $role)
                    !== ProjectRole::Observer->value,
            );
    }

    public function currentOrganizationRole(): ?OrganizationRole
    {
        if (! app()->bound(OrganizationContext::class)) {
            return null;
        }

        return app(OrganizationContext::class)->role();
    }
}
