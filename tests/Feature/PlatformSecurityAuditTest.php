<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\Organization;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_can_view_platform_security_audit(): void
    {
        $superadmin = User::factory()->administrator()->create();
        $ordinaryUser = User::factory()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->get(route('platform.security-audit.index'))
            ->assertOk()
            ->assertSee('Auditoria da plataforma');

        $this->actingAs($ordinaryUser)
            ->get(route('platform.security-audit.index'))
            ->assertForbidden();
    }

    public function test_audit_lists_context_without_exposing_metadata(): void
    {
        $organization = Organization::factory()->create(['name' => 'Organização Auditada']);
        $superadmin = User::factory()->administrator()->create(['name' => 'Superadmin Auditora']);
        $target = User::factory()->create(['name' => 'Conta Alvo']);

        SecurityAuditEvent::query()->create([
            'organization_id' => $organization->id,
            'actor_id' => $superadmin->id,
            'target_user_id' => $target->id,
            'request_id' => fake()->uuid(),
            'action' => 'password.platform_admin.temporary_reset',
            'result' => 'success',
            'environment' => 'local',
            'metadata' => [
                'password' => 'SEGREDO-NAO-PODE-APARECER',
                'token' => 'TOKEN-NAO-PODE-APARECER',
            ],
            'occurred_at' => now(),
        ]);

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->get(route('platform.security-audit.index'))
            ->assertOk()
            ->assertSee('password.platform_admin.temporary_reset')
            ->assertSee('Superadmin Auditora')
            ->assertSee('Conta Alvo')
            ->assertSee('Organização Auditada')
            ->assertSee('Sucesso')
            ->assertDontSee('SEGREDO-NAO-PODE-APARECER')
            ->assertDontSee('TOKEN-NAO-PODE-APARECER');
    }

    public function test_action_and_result_filters_limit_the_platform_trail(): void
    {
        $superadmin = User::factory()->administrator()->create();
        $this->event('mail.smtp.diagnostic', 'sent');
        $this->event('password.public.request', 'ignored');

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->get(route('platform.security-audit.index', [
                'action' => 'mail.smtp',
                'result' => 'sent',
            ]))
            ->assertOk()
            ->assertSee('mail.smtp.diagnostic')
            ->assertDontSee('password.public.request');
    }

    public function test_platform_audit_preserves_temporary_organization_context(): void
    {
        $organization = Organization::factory()->create();
        $superadmin = User::factory()->administrator()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)->withSession([
            EnsureOrganizationContext::SESSION_KEY => $organization->id,
            EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY => $organization->id,
        ])->get(route('platform.security-audit.index'))
            ->assertOk()
            ->assertSee('Acesso temporário da Superadmin')
            ->assertSee(route('platform.security-audit.index'), false)
            ->assertSee(route('audit.index'), false);
    }

    private function event(string $action, string $result): SecurityAuditEvent
    {
        return SecurityAuditEvent::query()->create([
            'request_id' => fake()->uuid(),
            'action' => $action,
            'result' => $result,
            'environment' => 'testing',
            'occurred_at' => now(),
        ]);
    }
}
