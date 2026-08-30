<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationSemanticTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_member_sees_semantic_groups_without_administration(): void
    {
        [$organization, $user] = $this->membership(OrganizationRole::Member);

        $this->actingAs($user)->withSession([EnsureOrganizationContext::SESSION_KEY => $organization->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Visão geral',
                'Painel',
                'Pendências documentais',
                'Calendário',
                'Negócios e portfólio',
                'Iniciativas',
                'Jornada comercial',
                'Contratos',
                'Gestão de projetos',
                'Projetos',
                'Requisitos',
                'Tarefas',
                'Kanban',
                'Documentação',
                'Testes',
                'Rastreabilidade',
            ])
            ->assertSee(route('tests.index'), false)
            ->assertSee(route('traceability.index'), false)
            ->assertDontSee('Clientes e unidades')
            ->assertDontSee('Equipe da organização')
            ->assertDontSee('Usuários da plataforma');
    }

    public function test_organization_administrator_sees_business_project_and_local_administration_groups(): void
    {
        [$organization, $user] = $this->membership(OrganizationRole::Administrator);

        $this->actingAs($user)->withSession([EnsureOrganizationContext::SESSION_KEY => $organization->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Negócios e portfólio',
                'Iniciativas',
                'Jornada comercial',
                'Contratos',
                'Clientes e unidades',
                'Gestão de projetos',
                'Projetos',
                'Administração',
                'Equipe da organização',
                'Modelos de documentos',
                'Auditoria',
            ])
            ->assertDontSee('Usuários da plataforma')
            ->assertDontSee('Auditoria da plataforma');
    }

    public function test_superadmin_without_active_organization_sees_only_platform_administration(): void
    {
        $superadmin = User::factory()->administrator()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)
            ->get(route('platform.organizations.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Administração da plataforma',
                'Organizações',
                'Usuários da plataforma',
                'Comunicação e SMTP',
                'Auditoria da plataforma',
            ])
            ->assertDontSee('Negócios e portfólio')
            ->assertDontSee('Gestão de projetos');
    }

    public function test_superadmin_temporary_access_combines_platform_and_organization_administration(): void
    {
        $organization = Organization::factory()->create();
        $superadmin = User::factory()->administrator()->create();

        $this->actingAsWithoutOrganizationProvisioning($superadmin)->withSession([
            EnsureOrganizationContext::SESSION_KEY => $organization->id,
            EnsureOrganizationContext::PLATFORM_ACCESS_SESSION_KEY => $organization->id,
        ])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Acesso temporário da Superadmin')
            ->assertSeeInOrder([
                'Administração',
                'Organizações',
                'Usuários da plataforma',
                'Comunicação e SMTP',
                'Auditoria da plataforma',
                'Equipe da organização',
                'Modelos de documentos',
                'Auditoria',
            ]);
    }

    /** @return array{0: Organization, 1: User} */
    private function membership(OrganizationRole $role): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_code' => $role,
            'status' => OrganizationMembershipStatus::Active,
        ]);

        return [$organization, $user];
    }
}
