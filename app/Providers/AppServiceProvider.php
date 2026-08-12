<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\Initiative;
use App\Models\InitiativeConfigurationVersion;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanTaskPosition;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectAttachment;
use App\Models\ProjectComment;
use App\Models\ProjectConfigurationVersion;
use App\Models\ProjectDocument;
use App\Models\ProjectMembership;
use App\Models\Requirement;
use App\Models\RequirementDependency;
use App\Models\RequirementVersion;
use App\Models\Scopes\OrganizationScope;
use App\Models\Task;
use App\Models\TaskHistory;
use App\Observers\OrganizationIntegrityObserver;
use App\Policies\OrganizationPolicy;
use App\Services\OrganizationContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OrganizationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $context = $this->app->make(OrganizationContext::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);

        View::share([
            'activeOrganization' => null,
            'activeOrganizationMembership' => null,
            'availableOrganizationMemberships' => collect(),
            'platformOrganizationAccess' => false,
        ]);

        foreach ([
            Client::class,
            Initiative::class,
            InitiativeConfigurationVersion::class,
            Project::class,
            ProjectConfigurationVersion::class,
            ProjectMembership::class,
            Requirement::class,
            RequirementVersion::class,
            RequirementDependency::class,
            Task::class,
            TaskHistory::class,
            KanbanBoard::class,
            KanbanColumn::class,
            KanbanTaskPosition::class,
            DocumentTemplate::class,
            ProjectDocument::class,
            ProjectComment::class,
            ProjectAttachment::class,
            ProjectActivity::class,
        ] as $model) {
            $model::observe(OrganizationIntegrityObserver::class);
            $model::addGlobalScope(new OrganizationScope($context));
        }

        OrganizationAuditEvent::addGlobalScope(new OrganizationScope($context));
    }
}
