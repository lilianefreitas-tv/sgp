<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_public_password_reset_screen_is_not_available(): void
    {
        $this->get('/reset-password/token-invalido')->assertNotFound();
    }

    public function test_password_cannot_be_reset_through_public_route(): void
    {
        $this->post('/reset-password', [
                'token' => 'token-invalido',
                'email' => 'usuario@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertNotFound();
    }
}
