<?php

namespace Tests\Feature;

use App\Enums\GlobalProfile;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_open_new_organization_form_after_temporary_access_correction(): void
    {
        $superadmin = User::factory()->administrator()->create();
        $candidate = User::factory()->create(['name' => 'Administradora candidata']);

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->get(route('platform.organizations.create'))
            ->assertOk()
            ->assertSee('Nova organização')
            ->assertSee('Administradora candidata')
            ->assertSee('Criar nova conta');

        $this->assertFalse($candidate->organizationMemberships()->exists());
    }

    public function test_superadmin_can_create_organization_with_existing_account_as_main_administrator(): void
    {
        $administrator = User::factory()->administrator()->create();
        $owner = User::factory()->create();

        $response = $this->actingAsWithoutOrganizationProvisioning($administrator)
            ->post(route('platform.organizations.store'), [
                'name' => 'Organização C',
                'slug' => 'organizacao-c',
                'type' => OrganizationType::Company->value,
                'timezone' => 'America/Belem',
                'account_mode' => 'existing',
                'administrator_user_id' => $owner->id,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $organization = Organization::query()->where('slug', 'organizacao-c')->firstOrFail();
        $response->assertRedirect(route('platform.organizations.edit', $organization));
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role_code' => OrganizationRole::Owner->value,
            'status' => OrganizationMembershipStatus::Active->value,
        ]);
        $this->assertSame(4, DocumentTemplate::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->count());
        $this->assertTrue(OrganizationAuditEvent::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('action', 'organization.create')
            ->exists());
    }

    public function test_superadmin_can_create_organization_and_new_main_administrator_account(): void
    {
        Notification::fake();
        $superadmin = User::factory()->administrator()->create();

        $response = $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->post(route('platform.organizations.store'), [
                'name' => 'Organização Nova',
                'slug' => 'organizacao-nova',
                'type' => OrganizationType::Company->value,
                'timezone' => 'America/Belem',
                'account_mode' => 'new',
                'new_user_name' => 'Administradora da Empresa',
                'new_user_email' => 'admin@empresa.test',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionMissing('activation_url');
        $organization = Organization::query()->where('slug', 'organizacao-nova')->firstOrFail();
        $administrator = User::query()->where('email', 'admin@empresa.test')->firstOrFail();

        $response->assertRedirect(route('platform.organizations.edit', $organization));
        Notification::assertSentTo($administrator, ResetPassword::class);
        $this->assertSame(GlobalProfile::User, $administrator->global_profile);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $administrator->id,
            'role_code' => OrganizationRole::Owner->value,
        ]);
    }

    public function test_common_user_cannot_access_platform_organization_administration(): void
    {
        $this->actingAsWithoutOrganizationProvisioning(User::factory()->create())
            ->get(route('platform.organizations.index'))
            ->assertForbidden();
    }

    public function test_platform_administrator_can_suspend_and_reactivate_organization(): void
    {
        $administrator = User::factory()->administrator()->create();
        $organization = Organization::factory()->create();

        $this->actingAsWithoutOrganizationProvisioning($administrator)
            ->put(route('platform.organizations.update', $organization), [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'type' => $organization->type->value,
                'timezone' => $organization->timezone,
                'status' => OrganizationStatus::Suspended->value,
            ])->assertRedirect();

        $this->assertSame(OrganizationStatus::Suspended, $organization->fresh()->status);

        $this->actingAsWithoutOrganizationProvisioning($administrator)
            ->put(route('platform.organizations.update', $organization), [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'type' => $organization->type->value,
                'timezone' => $organization->timezone,
                'status' => OrganizationStatus::Active->value,
            ])->assertRedirect();

        $this->assertSame(OrganizationStatus::Active, $organization->fresh()->status);
    }

    public function test_owner_can_link_existing_user_and_define_organization_role(): void
    {
        [$organization, $owner] = $this->tenantWithRole(OrganizationRole::Owner);
        $member = User::factory()->create(['email' => 'membro@sgp.test']);

        $this->actingAs($owner)->post(route('organization-members.store'), [
            'account_mode' => 'existing',
            'existing_user_email' => $member->email,
            'role_code' => OrganizationRole::Reader->value,
        ])->assertRedirect(route('organization-members.index'));

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role_code' => OrganizationRole::Reader->value,
            'status' => OrganizationMembershipStatus::Active->value,
        ]);
    }

    public function test_organization_administrator_can_create_common_account_for_own_team(): void
    {
        Notification::fake();
        [$organization, $administrator] = $this->tenantWithRole(OrganizationRole::Administrator);

        $response = $this->actingAs($administrator)->post(route('organization-members.store'), [
            'account_mode' => 'new',
            'new_user_name' => 'Nova Analista',
            'new_user_email' => 'analista@empresa.test',
            'role_code' => OrganizationRole::Member->value,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('organization-members.index'));
        $response->assertSessionMissing('activation_url');
        $user = User::query()->where('email', 'analista@empresa.test')->firstOrFail();
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertSame(GlobalProfile::User, $user->global_profile);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_code' => OrganizationRole::Member->value,
        ]);
    }

    public function test_organization_administrator_cannot_change_owner(): void
    {
        [$organization, $administrator] = $this->tenantWithRole(OrganizationRole::Administrator);
        $owner = User::factory()->create();
        $ownerMembership = $this->membership($organization, $owner, OrganizationRole::Owner);

        $this->actingAs($administrator)->patch(route('organization-members.update', $ownerMembership), [
            'role_code' => OrganizationRole::Member->value,
            'status' => OrganizationMembershipStatus::Active->value,
        ])->assertForbidden();

        $this->assertSame(OrganizationRole::Owner, $ownerMembership->fresh()->role_code);
    }

    public function test_last_active_owner_cannot_be_suspended_or_removed(): void
    {
        [$organization, $owner, $ownerMembership] = $this->tenantWithRole(OrganizationRole::Owner);

        $this->actingAs($owner)->patch(route('organization-members.update', $ownerMembership), [
            'role_code' => OrganizationRole::Owner->value,
            'status' => OrganizationMembershipStatus::Suspended->value,
        ])->assertStatus(422);

        $this->actingAs($owner)->delete(route('organization-members.destroy', $ownerMembership))
            ->assertStatus(422);

        $this->assertDatabaseHas('organization_memberships', [
            'id' => $ownerMembership->id,
            'organization_id' => $organization->id,
            'role_code' => OrganizationRole::Owner->value,
            'status' => OrganizationMembershipStatus::Active->value,
        ]);
    }

    public function test_membership_from_another_organization_cannot_be_changed_by_url(): void
    {
        [, $owner] = $this->tenantWithRole(OrganizationRole::Owner);
        $foreignOrganization = Organization::factory()->create();
        $foreignMembership = $this->membership(
            $foreignOrganization,
            User::factory()->create(),
            OrganizationRole::Member,
        );

        $this->actingAs($owner)->patch(route('organization-members.update', $foreignMembership), [
            'role_code' => OrganizationRole::Reader->value,
            'status' => OrganizationMembershipStatus::Active->value,
        ])->assertNotFound();

        $this->assertSame(OrganizationRole::Member, $foreignMembership->fresh()->role_code);
    }

    /** @return array{Organization, User, OrganizationMembership} */
    private function tenantWithRole(OrganizationRole $role): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $membership = $this->membership($organization, $user, $role);

        return [$organization, $user, $membership];
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
