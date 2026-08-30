<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_exposes_public_recovery_flow(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Esqueceu a senha?')
            ->assertSee(config('sgp.release_label'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Recuperação de acesso');
    }

    public function test_public_request_is_neutral_and_only_notifies_active_account(): void
    {
        Notification::fake();
        $active = User::factory()->create(['email' => 'ativa@sgp.test']);
        $inactive = User::factory()->create(['email' => 'inativa@sgp.test', 'is_active' => false]);

        foreach (['ativa@sgp.test', 'inativa@sgp.test', 'inexistente@sgp.test'] as $email) {
            $this->post(route('password.email'), ['email' => $email])
                ->assertSessionHas('status', 'Se existir uma conta ativa com esse e-mail, enviaremos as instruções de redefinição.');
        }

        Notification::assertSentTo($active, ResetPasswordNotification::class);
        Notification::assertNotSentTo($inactive, ResetPasswordNotification::class);
        $this->assertDatabaseCount('security_audit_events', 3);
        $this->assertSame(0, SecurityAuditEvent::query()
            ->get()
            ->filter(fn(SecurityAuditEvent $event) => str_contains(json_encode($event->metadata), 'token'))
            ->count());
    }

    public function test_superadmin_can_send_link_without_exposing_token_in_session(): void
    {
        Notification::fake();
        $superadmin = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->post(route('platform.users.password-reset-link', $target))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('activation_url');

        Notification::assertSentTo($target, ResetPasswordNotification::class);
        $this->assertDatabaseHas('security_audit_events', [
            'actor_id' => $superadmin->id,
            'target_user_id' => $target->id,
            'action' => 'password.platform_admin.request',
            'result' => 'sent',
        ]);
    }

    public function test_owner_and_organization_administrator_can_send_only_for_own_organization(): void
    {
        Notification::fake();
        [$organization, $owner, $ownerMembership] = $this->tenantWithRole(OrganizationRole::Owner);
        $target = User::factory()->create();
        $targetMembership = $this->membership($organization, $target, OrganizationRole::Member);
        $administrator = User::factory()->create();
        $this->membership($organization, $administrator, OrganizationRole::Administrator);
        $secondTarget = User::factory()->create();
        $secondTargetMembership = $this->membership($organization, $secondTarget, OrganizationRole::Reader);
        $foreignOrganization = Organization::factory()->create();
        $foreignMembership = $this->membership($foreignOrganization, User::factory()->create(), OrganizationRole::Member);

        $this->actingAs($owner)
            ->post(route('organization-members.password-reset-link', $targetMembership))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($owner)
            ->post(route('organization-members.password-reset-link', $foreignMembership))
            ->assertNotFound();

        $this->actingAs($administrator)
            ->post(route('organization-members.password-reset-link', $secondTargetMembership))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($target, ResetPasswordNotification::class);
        Notification::assertSentTo($secondTarget, ResetPasswordNotification::class);
        $this->assertDatabaseHas('security_audit_events', [
            'organization_id' => $organization->id,
            'actor_id' => $owner->id,
            'target_user_id' => $target->id,
            'action' => 'password.organization_admin.request',
        ]);
    }

    public function test_member_cannot_send_link_and_inactive_account_cannot_reset_password(): void
    {
        Notification::fake();
        [$organization, $member] = $this->tenantWithRole(OrganizationRole::Member);
        $target = User::factory()->create();
        $targetMembership = $this->membership($organization, $target, OrganizationRole::Member);

        $this->actingAs($member)
            ->post(route('organization-members.password-reset-link', $targetMembership))
            ->assertForbidden();

        $this->post(route('logout'));
        $this->assertGuest();

        $token = Password::broker()->createToken($target);
        $target->update(['is_active' => false]);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $target->email,
            'password' => 'NovaSenha@123',
            'password_confirmation' => 'NovaSenha@123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $target->fresh()->password));
    }

    public function test_new_token_invalidates_previous_token_expires_and_is_single_use(): void
    {
        $user = User::factory()->create();
        $firstToken = Password::broker()->createToken($user);
        $secondToken = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $firstToken,
            'email' => $user->email,
            'password' => 'NovaSenha@123',
            'password_confirmation' => 'NovaSenha@123',
        ])->assertSessionHasErrors('email');

        $this->post(route('password.store'), [
            'token' => $secondToken,
            'email' => $user->email,
            'password' => 'NovaSenha@123',
            'password_confirmation' => 'NovaSenha@123',
        ])->assertRedirect(route('login'));

        $this->post(route('password.store'), [
            'token' => $secondToken,
            'email' => $user->email,
            'password' => 'OutraSenha@123',
            'password_confirmation' => 'OutraSenha@123',
        ])->assertSessionHasErrors('email');

        $expiredToken = Password::broker()->createToken($user);
        $this->travel(61)->minutes();

        $this->post(route('password.store'), [
            'token' => $expiredToken,
            'email' => $user->email,
            'password' => 'OutraSenha@123',
            'password_confirmation' => 'OutraSenha@123',
        ])->assertSessionHasErrors('email');
    }

    /** @return array{Organization, User, OrganizationMembership} */
    private function tenantWithRole(OrganizationRole $role): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        return [$organization, $user, $this->membership($organization, $user, $role)];
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
