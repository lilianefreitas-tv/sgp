<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ProjectBaselineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_manager_can_constitute_sequential_immutable_baselines(): void
    {
        [$organization, $actor, $project] = $this->scenario();
        $requirement = Requirement::factory()->create(['project_id' => $project->id, 'title' => 'Título original']);

        $service = app(ProjectBaselineService::class);
        $first = $service->create($project, ['title' => 'Marco inicial', 'justification' => 'Escopo aprovado.', 'requirements' => [$requirement->id]], $actor);
        $requirement->update(['title' => 'Título alterado']);
        $second = $service->create($project, ['title' => 'Segundo marco', 'justification' => 'Nova referência.'], $actor);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame('Título original', $first->items()->where('item_type', 'requirement')->firstOrFail()->snapshot['title']);
        $this->assertDatabaseHas('project_baselines', ['organization_id' => $organization->id, 'project_id' => $project->id, 'version' => 2]);
    }

    public function test_member_can_view_but_only_manager_can_constitute_baseline(): void
    {
        [$organization, $manager, $project] = $this->scenario();
        $member = User::factory()->create();
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $member->id, 'role_code' => OrganizationRole::Member, 'status' => OrganizationMembershipStatus::Active]);
        $project->memberships()->create(['user_id' => $member->id, 'role' => 'observer', 'is_active' => true, 'started_at' => today()]);

        $this->actingAs($member)->withSession(['active_organization_id' => $organization->id])->get(route('projects.baselines.index', $project))->assertOk();
        $this->actingAs($member)->withSession(['active_organization_id' => $organization->id])->post(route('projects.baselines.store', $project), ['title' => 'Não autorizado', 'justification' => 'Teste'])->assertForbidden();
    }

    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'role_code' => OrganizationRole::Administrator, 'status' => OrganizationMembershipStatus::Active]);
        app(OrganizationContext::class)->activate($membership, collect([$membership]));
        $project = Project::factory()->create(['manager_id' => $actor->id]);

        return [$organization, $actor, $project];
    }
}
