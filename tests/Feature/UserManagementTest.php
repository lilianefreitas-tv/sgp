<?php

namespace Tests\Feature;

use App\Enums\GlobalProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_list_users(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Usuários');
    }

    public function test_regular_user_cannot_access_user_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_create_user(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('users.store'), [
                'name' => 'Maria da Silva',
                'email' => 'maria@example.com',
                'global_profile' => GlobalProfile::User->value,
                'is_active' => '1',
                'password' => 'Senha@123456',
                'password_confirmation' => 'Senha@123456',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'global_profile' => GlobalProfile::User->value,
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_update_user_without_changing_password(): void
    {
        $administrator = User::factory()->administrator()->create();
        $managedUser = User::factory()->create();
        $oldPassword = $managedUser->password;

        $this->actingAs($administrator)
            ->put(route('users.update', $managedUser), [
                'name' => 'Nome Atualizado',
                'email' => $managedUser->email,
                'global_profile' => GlobalProfile::User->value,
                'is_active' => '1',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertSame($oldPassword, $managedUser->fresh()->password);
        $this->assertSame('Nome Atualizado', $managedUser->fresh()->name);
    }

    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->from(route('users.edit', $administrator))
            ->put(route('users.update', $administrator), [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'global_profile' => GlobalProfile::Administrator->value,
                'is_active' => '0',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('users.edit', $administrator))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($administrator->fresh()->is_active);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
