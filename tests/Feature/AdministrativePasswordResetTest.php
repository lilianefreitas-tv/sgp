<?php

namespace Tests\Feature;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdministrativePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_generates_one_time_password_and_revokes_previous_access(): void
    {
        $superadmin = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $oldPassword = $target->password;
        Password::broker()->createToken($target);
        DB::table('sessions')->insert([
            'id' => 'target-active-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('platform.users.temporary-password', $target));

        $response->assertOk()
            ->assertViewIs('platform.users.temporary-password')
            ->assertHeader('Cache-Control');
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );

        $temporaryPassword = $response->viewData('temporaryPassword');
        $freshTarget = $target->fresh();

        $this->assertIsString($temporaryPassword);
        $this->assertSame(20, strlen($temporaryPassword));
        $this->assertNotSame($oldPassword, $freshTarget->password);
        $this->assertTrue(Hash::check($temporaryPassword, $freshTarget->password));
        $this->assertTrue($freshTarget->must_change_password);
        $this->assertNotNull($freshTarget->temporary_password_issued_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-active-session']);

        $event = SecurityAuditEvent::query()
            ->where('action', 'password.platform_admin.temporary_reset')
            ->sole();
        $this->assertSame($superadmin->id, $event->actor_id);
        $this->assertSame($target->id, $event->target_user_id);
        $this->assertStringNotContainsString($temporaryPassword, json_encode($event->toArray()));
    }

    public function test_only_superadmin_can_generate_temporary_password(): void
    {
        $member = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($member)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('platform.users.temporary-password', $target))
            ->assertForbidden();

        $this->assertFalse($target->fresh()->must_change_password);
        $this->assertDatabaseMissing('security_audit_events', [
            'action' => 'password.platform_admin.temporary_reset',
        ]);
    }

    public function test_superadmin_cannot_reset_self_or_inactive_account(): void
    {
        $superadmin = User::factory()->administrator()->create();
        $inactive = User::factory()->inactive()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('platform.users.temporary-password', $superadmin))
            ->assertSessionHasErrors('user');

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('platform.users.temporary-password', $inactive))
            ->assertSessionHasErrors('user');

        $this->assertFalse($inactive->fresh()->must_change_password);
    }

    public function test_recent_password_confirmation_is_required_for_administrative_reset(): void
    {
        $superadmin = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->post(route('platform.users.temporary-password', $target))
            ->assertRedirect(route('password.confirm'));

        $this->assertFalse($target->fresh()->must_change_password);
    }
}
