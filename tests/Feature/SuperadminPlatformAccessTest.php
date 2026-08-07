<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminPlatformAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_without_membership_is_redirected_to_platform_administration(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAsWithoutOrganizationProvisioning($administrator)
            ->get(route('dashboard'))
            ->assertRedirect(route('platform.organizations.index'))
            ->assertSessionMissing(EnsureOrganizationContext::SESSION_KEY)
            ->assertSessionMissing(EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY);
    }

    public function test_superadmin_without_membership_logs_in_to_platform_administration(): void
    {
        $administrator = User::factory()->administrator()->create([
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => $administrator->email,
            'password' => 'password',
        ])->assertRedirect(route('platform.organizations.index'));
    }

    public function test_common_user_without_membership_remains_blocked(): void
    {
        $this->actingAsWithoutOrganizationProvisioning(User::factory()->create())
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_superadmin_can_access_active_organization_without_creating_membership(): void
    {
        $administrator = User::factory()->administrator()->create();
        $organization = Organization::factory()->create([
            'name' => 'Organização acessada pela plataforma',
            'status' => OrganizationStatus::Active,
        ]);

        $this->actingAsWithoutOrganizationProvisioning($administrator)
            ->post(route('platform.organizations.access', $organization))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(EnsureOrganizationContext::SESSION_KEY, $organization->id)
            ->assertSessionHas(EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY, $organization->id);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee($organization->name)
            ->assertSee('Acesso temporário da Superadmin');

        $this->get(route('organization-members.index'))->assertOk();

        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $administrator->id,
        ]);
        $this->assertTrue(OrganizationAuditEvent::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('actor_id', $administrator->id)
            ->where('action', 'platform.organization.access')
            ->where('result', 'success')
            ->exists());
    }

    public function test_superadmin_can_leave_temporary_organization_access(): void
    {
        $administrator = User::factory()->administrator()->create();
        $organization = Organization::factory()->create();

        $this->actingAsWithoutOrganizationProvisioning($administrator)
            ->withSession([
                EnsureOrganizationContext::SESSION_KEY => $organization->id,
                EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY => $organization->id,
            ])
            ->delete(route('platform.organization-access.leave'))
            ->assertRedirect(route('platform.organizations.index'))
            ->assertSessionMissing(EnsureOrganizationContext::SESSION_KEY)
            ->assertSessionMissing(EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY);

        $this->assertTrue(OrganizationAuditEvent::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('action', 'platform.organization.leave')
            ->where('result', 'success')
            ->exists());
    }

    public function test_superadmin_cannot_start_access_to_suspended_organization(): void
    {
        $administrator = User::factory()->administrator()->create();
        $organization = Organization::factory()->create([
            'status' => OrganizationStatus::Suspended,
        ]);

        $this->actingAsWithoutOrganizationProvisioning($administrator)
            ->post(route('platform.organizations.access', $organization))
            ->assertStatus(422)
            ->assertSessionMissing(EnsureOrganizationContext::SESSION_KEY)
            ->assertSessionMissing(EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY);
    }

    public function test_superadmin_removing_own_last_membership_returns_to_platform(): void
    {
        $administrator = User::factory()->administrator()->create();
        $otherOwner = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $otherOwner->id,
            'role_code' => OrganizationRole::Owner,
            'status' => OrganizationMembershipStatus::Active,
            'is_default' => true,
        ]);
        $membership = OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $administrator->id,
            'role_code' => OrganizationRole::Administrator,
            'status' => OrganizationMembershipStatus::Active,
            'is_default' => true,
        ]);

        $this->actingAs($administrator)
            ->withSession([
                EnsureOrganizationContext::SESSION_KEY => $organization->id,
            ])
            ->delete(route('organization-members.destroy', $membership))
            ->assertRedirect(route('organization-members.index'));

        $this->assertDatabaseMissing('organization_memberships', [
            'id' => $membership->id,
        ]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('platform.organizations.index'))
            ->assertSessionMissing(EnsureOrganizationContext::SESSION_KEY)
            ->assertSessionMissing(EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY);
    }
}
