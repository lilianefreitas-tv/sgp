<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RequiredPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_password_login_goes_directly_to_required_change(): void
    {
        $user = User::factory()->create([
            'password' => 'Temporaria@123',
            'must_change_password' => true,
            'temporary_password_issued_at' => now(),
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'Temporaria@123',
        ])->assertRedirect(route('password.required.edit'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_forced_user_cannot_bypass_change_but_can_logout(): void
    {
        Organization::factory()->create();
        $user = User::factory()->create([
            'must_change_password' => true,
            'temporary_password_issued_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.required.edit'));

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_required_change_rejects_same_password_and_completes_with_a_new_one(): void
    {
        $user = User::factory()->create([
            'password' => 'Temporaria@123',
            'must_change_password' => true,
            'temporary_password_issued_at' => now(),
        ]);

        $this->actingAs($user)->put(route('password.required.update'), [
            'current_password' => 'Temporaria@123',
            'password' => 'Temporaria@123',
            'password_confirmation' => 'Temporaria@123',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put(route('password.required.update'), [
            'current_password' => 'Temporaria@123',
            'password' => 'Definitiva@456',
            'password_confirmation' => 'Definitiva@456',
        ])->assertRedirect(route('dashboard'));

        $freshUser = $user->fresh();
        $this->assertFalse($freshUser->must_change_password);
        $this->assertNull($freshUser->temporary_password_issued_at);
        $this->assertNotNull($freshUser->password_changed_at);
        $this->assertTrue(Hash::check('Definitiva@456', $freshUser->password));
        $this->assertDatabaseHas('security_audit_events', [
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'action' => 'password.required_change.completed',
            'result' => 'success',
        ]);
    }

    public function test_email_reset_clears_an_outstanding_temporary_password_requirement(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'temporary_password_issued_at' => now(),
        ]);
        $token = app('auth.password.broker')->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Recuperada@789',
            'password_confirmation' => 'Recuperada@789',
        ])->assertRedirect(route('login'));

        $freshUser = $user->fresh();
        $this->assertFalse($freshUser->must_change_password);
        $this->assertNull($freshUser->temporary_password_issued_at);
        $this->assertNotNull($freshUser->password_changed_at);
    }
}
