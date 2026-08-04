<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_password_reset_link_screen_is_not_available(): void
    {
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_public_password_reset_link_cannot_be_requested(): void
    {
        $this->post('/forgot-password', [
            'email' => 'usuario@example.com',
        ])->assertNotFound();
    }

    public function test_invited_user_can_open_activation_link_and_define_password(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))->assertOk();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NovaSenha123!',
            'password_confirmation' => 'NovaSenha123!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NovaSenha123!', $user->fresh()->password));
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $this->post('/reset-password', [
                'token' => 'token-invalido',
                'email' => 'usuario@example.com',
                'password' => 'NovaSenha123!',
                'password_confirmation' => 'NovaSenha123!',
            ])
            ->assertSessionHasErrors('email');
    }
}
