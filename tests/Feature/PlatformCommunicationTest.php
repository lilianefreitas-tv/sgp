<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\Organization;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use App\Notifications\SmtpDiagnosticNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlatformCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_can_view_transactional_configuration(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('platform.communication.index'))
            ->assertForbidden();
    }

    public function test_configuration_page_never_exposes_smtp_password(): void
    {
        $superadmin = User::factory()->administrator()->create();
        $this->readyMailConfiguration('segredo-nao-pode-aparecer');

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->get(route('platform.communication.index'))
            ->assertOk()
            ->assertSee('Configuração apta para teste')
            ->assertSee('Configuração segura com Resend')
            ->assertSee('Configurada e protegida')
            ->assertDontSee('segredo-nao-pode-aparecer');
    }

    public function test_invalid_resend_username_and_scheme_are_diagnosed_before_delivery(): void
    {
        $superadmin = User::factory()->administrator()->create();
        $this->readyMailConfiguration('segredo-nao-pode-aparecer');
        config([
            'mail.mailers.smtp.host' => 'smtp.resend.com',
            'mail.mailers.smtp.scheme' => 'tls',
            'mail.mailers.smtp.username' => 'apismtp',
        ]);

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->get(route('platform.communication.index'))
            ->assertOk()
            ->assertSee('Configuração pendente')
            ->assertSee('MAIL_SCHEME deve ser smtp')
            ->assertSee('MAIL_USERNAME=resend')
            ->assertDontSee('segredo-nao-pode-aparecer');
    }

    public function test_communication_page_preserves_temporary_organization_navigation(): void
    {
        $organization = Organization::factory()->create();
        $superadmin = User::factory()->administrator()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)->withSession([
            EnsureOrganizationContext::SESSION_KEY => $organization->id,
            EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY => $organization->id,
        ])->get(route('platform.communication.index'))
            ->assertOk()
            ->assertSee('Acesso temporário da Superadmin')
            ->assertSee('Gestão de projetos')
            ->assertSee('Comunicação e SMTP');
    }

    public function test_superadmin_can_process_smtp_diagnostic_with_sanitized_audit(): void
    {
        Notification::fake();
        $superadmin = User::factory()->administrator()->create();
        $this->readyMailConfiguration('segredo-smtp');

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->post(route('platform.communication.test'), ['recipient' => 'destino@sgp.dev.br'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentOnDemand(SmtpDiagnosticNotification::class);
        $event = SecurityAuditEvent::query()->where('action', 'mail.smtp.diagnostic')->sole();
        $this->assertSame('sent', $event->result);
        $this->assertArrayHasKey('recipient_sha256', $event->metadata);
        $this->assertStringNotContainsString('destino@sgp.dev.br', json_encode($event->toArray()));
        $this->assertStringNotContainsString('segredo-smtp', json_encode($event->toArray()));
    }

    public function test_diagnostic_is_blocked_until_configuration_is_ready(): void
    {
        Notification::fake();
        $superadmin = User::factory()->administrator()->create();
        config([
            'mail.default' => 'log',
            'mail.from.address' => 'hello@example.com',
        ]);

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->post(route('platform.communication.test'), ['recipient' => 'destino@sgp.dev.br'])
            ->assertSessionHasErrors('recipient');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('security_audit_events', ['action' => 'mail.smtp.diagnostic']);
    }

    public function test_password_recovery_notification_is_dispatched_through_the_queue(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'fila@sgp.dev.br']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification): bool => $notification instanceof \Illuminate\Contracts\Queue\ShouldQueue,
        );
    }

    private function readyMailConfiguration(string $password): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'nao-responda@sgp.dev.br',
            'mail.from.name' => 'PRISMA SGP',
            'mail.mailers.smtp.host' => 'smtp.provedor.test',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => 'resend',
            'mail.mailers.smtp.password' => $password,
            'queue.default' => 'database',
        ]);
    }
}
