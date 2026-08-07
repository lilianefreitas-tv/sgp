<?php

namespace Tests\Feature;

use App\Enums\GlobalProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_without_organization_can_list_platform_users(): void
    {
        $superadmin = User::factory()->administrator()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->get(route('platform.users.index'))
            ->assertOk()
            ->assertSee('Usuários da plataforma')
            ->assertSee('Conta global não é vínculo organizacional');
    }

    public function test_platform_navigation_shows_organizations_and_users_without_active_tenant(): void
    {
        $superadmin = User::factory()->administrator()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->get(route('platform.organizations.index'))
            ->assertOk()
            ->assertSee('Organizações')
            ->assertSee('Usuários da plataforma');
    }

    public function test_regular_user_cannot_access_platform_user_management(): void
    {
        $user = User::factory()->create();

        $this->actingAsWithoutOrganizationProvisioning($user)
            ->get(route('platform.users.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_create_global_common_account_without_membership(): void
    {
        Notification::fake();
        $superadmin = User::factory()->administrator()->create();

        $response = $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->post(route('platform.users.store'), [
                'new_user_name' => 'Maria da Silva',
                'new_user_email' => 'maria@example.com',
                'global_profile' => GlobalProfile::User->value,
            ]);

        $response->assertRedirect(route('platform.users.index'))
            ->assertSessionMissing('activation_url');

        $created = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame(GlobalProfile::User, $created->global_profile);
        $this->assertTrue($created->is_active);
        $this->assertFalse($created->organizationMemberships()->exists());
        Notification::assertSentTo($created, ResetPassword::class);
    }

    public function test_superadmin_can_create_another_superadmin_account(): void
    {
        $superadmin = User::factory()->administrator()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->post(route('platform.users.store'), [
                'new_user_name' => 'Superadmin de Apoio',
                'new_user_email' => 'apoio@sgp.test',
                'global_profile' => GlobalProfile::Administrator->value,
            ])
            ->assertRedirect(route('platform.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'apoio@sgp.test',
            'global_profile' => GlobalProfile::Administrator->value,
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_update_global_account_without_changing_memberships(): void
    {
        $superadmin = User::factory()->administrator()->create();
        $managedUser = User::factory()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->put(route('platform.users.update', $managedUser), [
                'name' => 'Nome Atualizado',
                'email' => $managedUser->email,
                'global_profile' => GlobalProfile::User->value,
                'is_active' => '1',
            ])
            ->assertRedirect(route('platform.users.index'));

        $this->assertSame('Nome Atualizado', $managedUser->fresh()->name);
        $this->assertFalse($managedUser->organizationMemberships()->exists());
    }

    public function test_superadmin_cannot_deactivate_own_account(): void
    {
        $superadmin = User::factory()->administrator()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->from(route('platform.users.edit', $superadmin))
            ->put(route('platform.users.update', $superadmin), [
                'name' => $superadmin->name,
                'email' => $superadmin->email,
                'global_profile' => GlobalProfile::Administrator->value,
                'is_active' => '0',
            ])
            ->assertRedirect(route('platform.users.edit', $superadmin))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($superadmin->fresh()->is_active);
    }

    public function test_inactive_user_cannot_authenticate_or_keep_an_active_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
