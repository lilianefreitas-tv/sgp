<?php

namespace App\Models;

use App\Enums\GlobalProfile;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function assignedRequirements(): HasMany
    {
        return $this->hasMany(Requirement::class, 'responsible_id');
    }

    public function hasProjectRole(\App\Enums\ProjectRole $role, ?Project $project = null): bool
    {
        return $this->projectMemberships()
            ->where('role', $role->value)
            ->where('is_active', true)
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
            ->exists();
    }

    public function canCreateProjects(): bool
    {
        return $this->isAdministrator()
            || $this->hasProjectRole(\App\Enums\ProjectRole::ProjectManager);
    }
}
