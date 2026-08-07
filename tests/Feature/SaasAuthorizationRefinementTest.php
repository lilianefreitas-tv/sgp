<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasAuthorizationRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_administrator_sees_all_projects_only_in_own_organization(): void
    {
        $organizationA = Organization::factory()->create(['name' => 'Organização A']);
        $organizationB = Organization::factory()->create(['name' => 'Organização B']);
        $administrator = User::factory()->create();
        $this->membership($organizationA, $administrator, OrganizationRole::Administrator);

        $projectA = Project::factory()->create([
            'organization_id' => $organizationA->id,
            'client_id' => null,
            'name' => 'Projeto visível da organização A',
        ]);
        $projectB = Project::factory()->create([
            'organization_id' => $organizationB->id,
            'client_id' => null,
            'name' => 'Projeto secreto da organização B',
        ]);

        $this->actingAs($administrator)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($projectA->name)
            ->assertDontSee($projectB->name);

        $this->actingAs($administrator)->get(route('projects.show', $projectA))->assertOk();
        $this->actingAs($administrator)->get('/projects/'.$projectB->id)->assertNotFound();
    }

    public function test_common_user_sees_only_projects_where_they_participate(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $this->membership($organization, $user, OrganizationRole::Member);
        $visible = Project::factory()->create(['organization_id' => $organization->id, 'client_id' => null, 'name' => 'Projeto atribuído']);
        $hidden = Project::factory()->create(['organization_id' => $organization->id, 'client_id' => null, 'name' => 'Projeto não atribuído']);
        ProjectMembership::query()->create([
            'project_id' => $visible->id,
            'user_id' => $user->id,
            'role' => ProjectRole::Developer,
            'is_active' => true,
            'started_at' => today(),
        ]);

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);
    }

    public function test_project_team_cannot_receive_user_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $administrator = User::factory()->create();
        $foreignUser = User::factory()->create();
        $this->membership($organizationA, $administrator, OrganizationRole::Administrator);
        $this->membership($organizationB, $foreignUser, OrganizationRole::Member);
        $project = Project::factory()->create(['organization_id' => $organizationA->id, 'client_id' => null]);

        $this->actingAs($administrator)->post(route('projects.members.store', $project), [
            'user_id' => $foreignUser->id,
            'roles' => [ProjectRole::Developer->value],
        ])->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $foreignUser->id,
        ]);
    }

    public function test_superadmin_platform_access_is_explicit_and_audited(): void
    {
        $organization = Organization::factory()->create();
        $superadmin = User::factory()->administrator()->create();

        $response = $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->post(route('platform.organizations.access', $organization));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas(EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY, $organization->id);
        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $superadmin->id,
        ]);
        $this->assertTrue(OrganizationAuditEvent::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('actor_id', $superadmin->id)
            ->where('action', 'platform.organization.access')
            ->exists());
    }

    public function test_project_sequence_remains_independent_per_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $firstA = Project::factory()->create(['organization_id' => $organizationA->id, 'client_id' => null]);
        $secondA = Project::factory()->create(['organization_id' => $organizationA->id, 'client_id' => null]);
        $firstB = Project::factory()->create(['organization_id' => $organizationB->id, 'client_id' => null]);

        $this->assertSame('PRJ-0001', $firstA->code);
        $this->assertSame('PRJ-0002', $secondA->code);
        $this->assertSame('PRJ-0001', $firstB->code);
    }

    private function membership(
        Organization $organization,
        User $user,
        OrganizationRole $role,
    ): OrganizationMembership {
        return OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_code' => $role,
            'status' => OrganizationMembershipStatus::Active,
            'is_default' => true,
            'joined_at' => now(),
        ]);
    }
}
