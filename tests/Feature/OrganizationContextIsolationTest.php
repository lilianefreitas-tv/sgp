<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\ProjectRole;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationContextIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_active_membership_defines_the_organization_context(): void
    {
        [$user, $first, $second] = $this->userWithTwoOrganizations();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas(EnsureOrganizationContext::SESSION_KEY, $second->id)
            ->assertSee($second->name)
            ->assertDontSee($first->name.' como organização ativa');
    }

    public function test_project_list_only_contains_records_from_the_active_organization(): void
    {
        [$administrator, $first, $second] = $this->administratorWithTwoOrganizations();
        $firstProject = $this->projectIn($first, 'Projeto da Organização A');
        $secondProject = $this->projectIn($second, 'Projeto confidencial da Organização B');

        $this->actingAs($administrator)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $first->id])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($firstProject->name)
            ->assertDontSee($secondProject->name);
    }

    public function test_route_model_binding_does_not_reveal_a_project_from_another_organization(): void
    {
        [$administrator, $first, $second] = $this->administratorWithTwoOrganizations();
        $foreignProject = $this->projectIn($second, 'Projeto fora do contexto');

        $this->actingAs($administrator)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $first->id])
            ->get(route('projects.show', $foreignProject))
            ->assertNotFound();
    }

    public function test_requirement_search_does_not_return_another_organization(): void
    {
        [$administrator, $first, $second] = $this->administratorWithTwoOrganizations();
        $firstProject = $this->projectIn($first, 'Projeto A');
        $secondProject = $this->projectIn($second, 'Projeto B');
        Requirement::factory()->create([
            'organization_id' => $first->id,
            'project_id' => $firstProject->id,
            'title' => 'Requisito permitido',
        ]);
        Requirement::factory()->create([
            'organization_id' => $second->id,
            'project_id' => $secondProject->id,
            'title' => 'Requisito secreto do outro tenant',
        ]);

        $this->actingAs($administrator)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $first->id])
            ->get(route('requirements.index'))
            ->assertOk()
            ->assertSee('Requisito permitido')
            ->assertDontSee('Requisito secreto do outro tenant');
    }

    public function test_creation_uses_the_session_context_and_rejects_a_foreign_organization_id(): void
    {
        [$administrator, $first, $second] = $this->administratorWithTwoOrganizations();

        $this->actingAs($administrator)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $first->id])
            ->post(route('clients.store'), [
                'organization_id' => $second->id,
                'name' => 'Cliente criado no contexto seguro',
                'type' => ClientType::Institution->value,
                'is_active' => '1',
            ])
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'organization_id' => $first->id,
            'name' => 'Cliente criado no contexto seguro',
        ]);
        $this->assertDatabaseMissing('clients', [
            'organization_id' => $second->id,
            'name' => 'Cliente criado no contexto seguro',
        ]);
    }

    public function test_user_can_switch_to_another_authorized_organization(): void
    {
        [$user, $first, $second] = $this->userWithTwoOrganizations();

        $this->actingAs($user)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $first->id])
            ->put(route('organization-context.update'), ['organization_id' => $second->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(EnsureOrganizationContext::SESSION_KEY, $second->id);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee($second->name);
    }

    public function test_user_cannot_switch_to_an_organization_without_membership(): void
    {
        $organization = Organization::factory()->create(['name' => 'Organização permitida']);
        $foreign = Organization::factory()->create(['name' => 'Organização proibida']);
        $user = User::factory()->create();
        $this->membership($user, $organization, OrganizationRole::Member, true);

        $this->actingAs($user)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organization->id])
            ->put(route('organization-context.update'), ['organization_id' => $foreign->id])
            ->assertForbidden()
            ->assertSessionHas(EnsureOrganizationContext::SESSION_KEY, $organization->id);
    }

    public function test_suspended_organization_does_not_create_an_operational_context(): void
    {
        $organization = Organization::factory()->create([
            'status' => OrganizationStatus::Suspended,
        ]);
        $user = User::factory()->create();
        $this->membership($user, $organization, OrganizationRole::Member, true);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_reader_role_cannot_write_even_with_a_project_role(): void
    {
        $organization = Organization::factory()->create();
        $reader = User::factory()->create();
        $this->membership($reader, $organization, OrganizationRole::Reader, true);
        $project = $this->projectIn($organization, 'Projeto somente leitura');
        ProjectMembership::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $reader->id,
            'role' => ProjectRole::ProjectManager,
            'is_active' => true,
            'started_at' => today(),
        ]);

        $this->actingAs($reader)
            ->withSession([EnsureOrganizationContext::SESSION_KEY => $organization->id])
            ->post(route('clients.store'), [
                'name' => 'Cliente não autorizado',
                'type' => ClientType::Institution->value,
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('clients', ['name' => 'Cliente não autorizado']);
    }

    public function test_legacy_membership_command_supports_dry_run_and_idempotent_write(): void
    {
        $organization = Organization::query()->firstOrFail();
        $user = User::factory()->create();

        $this->artisan('sgp:sync-organization-memberships', [
            'organization' => $organization->slug,
            '--dry-run' => true,
        ])->assertSuccessful();
        $this->assertDatabaseMissing('organization_memberships', ['user_id' => $user->id]);

        $this->artisan('sgp:sync-organization-memberships', [
            'organization' => $organization->slug,
            '--force' => true,
        ])->assertSuccessful();
        $this->artisan('sgp:sync-organization-memberships', [
            'organization' => $organization->slug,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_code' => OrganizationRole::Member->value,
            'status' => OrganizationMembershipStatus::Active->value,
            'is_default' => true,
        ]);
        $this->assertSame(1, OrganizationMembership::query()->where('user_id', $user->id)->count());
    }

    /** @return array{User, Organization, Organization} */
    private function userWithTwoOrganizations(): array
    {
        $first = Organization::factory()->create(['name' => 'Organização A']);
        $second = Organization::factory()->create(['name' => 'Organização B']);
        $user = User::factory()->create();
        $this->membership($user, $first, OrganizationRole::Member, false);
        $this->membership($user, $second, OrganizationRole::Member, true);

        return [$user, $first, $second];
    }

    /** @return array{User, Organization, Organization} */
    private function administratorWithTwoOrganizations(): array
    {
        $first = Organization::factory()->create(['name' => 'Organização A']);
        $second = Organization::factory()->create(['name' => 'Organização B']);
        $user = User::factory()->administrator()->create();
        $this->membership($user, $first, OrganizationRole::Administrator, true);
        $this->membership($user, $second, OrganizationRole::Reader, false);

        return [$user, $first, $second];
    }

    private function membership(
        User $user,
        Organization $organization,
        OrganizationRole $role,
        bool $default,
    ): OrganizationMembership {
        return OrganizationMembership::factory()
            ->for($user)
            ->for($organization)
            ->create([
                'role_code' => $role,
                'status' => OrganizationMembershipStatus::Active,
                'is_default' => $default,
            ]);
    }

    private function projectIn(Organization $organization, string $name): Project
    {
        $client = Client::factory()->create(['organization_id' => $organization->id]);

        return Project::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'name' => $name,
        ]);
    }
}
