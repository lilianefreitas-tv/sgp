<?php

namespace Tests\Feature;

use App\Enums\GlobalProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstAdministratorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_install_has_no_default_credentials_and_creates_only_the_first_administrator(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 0);

        $this->artisan('sgp:create-first-administrator', [
            '--name' => 'Administradora do SGP',
            '--email' => 'admin@example.com',
        ])
            ->expectsQuestion('Senha', 'Senha@123456')
            ->expectsQuestion('Confirme a senha', 'Senha@123456')
            ->assertSuccessful();

        $administrator = User::query()->sole();

        $this->assertSame('Administradora do SGP', $administrator->name);
        $this->assertSame('admin@example.com', $administrator->email);
        $this->assertSame(GlobalProfile::Administrator, $administrator->global_profile);
        $this->assertTrue($administrator->is_active);
        $this->assertTrue(Hash::check('Senha@123456', $administrator->password));

        $this->artisan('sgp:create-first-administrator', [
            '--name' => 'Outro administrador',
            '--email' => 'outro@example.com',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_non_interactive_creation_uses_protected_configuration_password(): void
    {
        config(['sgp.bootstrap.administrator_password' => 'Senha@123456']);

        $this->artisan('sgp:create-first-administrator', [
            '--name' => 'Administradora da Produção',
            '--email' => 'producao@example.com',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $administrator = User::query()->sole();

        $this->assertSame('Administradora da Produção', $administrator->name);
        $this->assertSame('producao@example.com', $administrator->email);
        $this->assertTrue(Hash::check('Senha@123456', $administrator->password));
    }

    public function test_non_interactive_creation_fails_without_protected_password(): void
    {
        config(['sgp.bootstrap.administrator_password' => null]);

        $this->artisan('sgp:create-first-administrator', [
            '--name' => 'Administradora da Produção',
            '--email' => 'producao@example.com',
            '--no-interaction' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_non_interactive_creation_still_validates_required_identity(): void
    {
        config(['sgp.bootstrap.administrator_password' => 'Senha@123456']);

        $this->artisan('sgp:create-first-administrator', [
            '--no-interaction' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
