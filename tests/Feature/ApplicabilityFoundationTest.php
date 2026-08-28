<?php

namespace Tests\Feature;

use App\Enums\ApplicabilityTargetType;
use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectMethodology;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use App\Services\AdaptiveConfigurationResolver;
use App\Services\InitiativeConfigurationService;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ApplicabilityFoundationTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { app(OrganizationContext::class)->clear(); parent::tearDown(); }
    public function test_resolver_uses_the_version_valid_at_the_requested_instant(): void
    {
        [$organization, $actor] = $this->actor(); $service = app(InitiativeConfigurationService::class);
        $initiative = $service->create($this->attributes(), $actor, 'Inicial'); $first = $initiative->configurationVersions()->firstOrFail();
        $this->travel(1)->second();
        $service->change($initiative, ['management_level' => ManagementLevel::Complete], $actor, 'Nova governança'); $second = $initiative->configurationVersions()->latest('sequence')->firstOrFail();
        $resolver = app(AdaptiveConfigurationResolver::class);
        $before = $resolver->initiative($initiative, ApplicabilityTargetType::Module, 'governance.baseline', $first->effective_from, '1.0.0');
        $after = $resolver->initiative($initiative, ApplicabilityTargetType::Module, 'governance.baseline', $second->effective_from, '1.0.0');
        $this->assertSame('essential', $before->dimensions['management_level']); $this->assertSame('complete', $after->dimensions['management_level']);
    }
    public function test_project_without_history_is_not_reinterpreted(): void
    {
        [$organization, $actor] = $this->actor(); $project = Project::factory()->create(['organization_id' => $organization->id, 'manager_id' => $actor->id]);
        $this->expectException(LogicException::class);
        app(AdaptiveConfigurationResolver::class)->project($project, ApplicabilityTargetType::Action, 'project.configuration.update', now(), '1.0.0');
    }
    private function actor(): array { $organization = Organization::factory()->create(); $user = User::factory()->create(); $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_code' => OrganizationRole::Administrator, 'status' => OrganizationMembershipStatus::Active]); app(OrganizationContext::class)->activate($membership, collect([$membership])); return [$organization, $user]; }
    private function attributes(): array { return ['title' => 'Iniciativa', 'origin' => InitiativeOrigin::Internal, 'execution_nature' => ExecutionNature::Internal, 'financial_management_mode' => FinancialManagementMode::NotApplicable, 'management_level' => ManagementLevel::Essential, 'methodology' => ProjectMethodology::Kanban]; }
}
