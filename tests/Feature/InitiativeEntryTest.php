<?php

namespace Tests\Feature;

use App\Enums\GlobalProfile;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\InitiativeConfigurationService;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitiativeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(OrganizationContext::class)->clear();
        parent::tearDown();
    }

    public function test_create_page_is_available_and_excludes_simplified(): void
    {
        [$organization, $user] = $this->actor();
        $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
            ->get(route('initiatives.create'))->assertOk()->assertSee('Nova iniciativa')->assertDontSee('Simplificado');
    }

    public function test_internal_and_commercial_initiatives_are_created_with_initial_version(): void
    {
        [$organization, $user] = $this->actor();
        foreach (['internal', 'commercial'] as $origin) {
            $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
                ->post(route('initiatives.store'), $this->payload(['origin' => $origin]))->assertRedirect(route('initiatives.index'));
        }
        $this->assertSame(2, Initiative::query()->count());
        $initiative = Initiative::query()->where('origin', 'commercial')->firstOrFail();
        $this->assertSame($organization->id, $initiative->organization_id);
        $this->assertDatabaseHas('initiative_configuration_versions', ['initiative_id' => $initiative->id, 'sequence' => 1, 'origin' => 'commercial', 'execution_nature' => 'internal', 'financial_management_mode' => 'not_applicable', 'management_level' => 'essential', 'methodology' => 'kanban']);
    }

    public function test_simplified_and_missing_required_fields_are_rejected(): void
    {
        [$organization, $user] = $this->actor();
        $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
            ->post(route('initiatives.store'), $this->payload(['management_level' => 'simplified']))->assertSessionHasErrors('initiative');
        $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])
            ->post(route('initiatives.store'), [])->assertSessionHasErrors(['title', 'origin', 'justification']);
    }

    public function test_context_isolation_suspended_membership_and_superadmin_access_are_enforced(): void
    {
        [$organization, $user, $membership] = $this->actor();
        $other = Organization::factory()->create();
        $this->actingAs($user)->withSession(['active_organization_id' => $other->id])->post(route('initiatives.store'), $this->payload())->assertRedirect();
        $this->assertDatabaseHas('initiatives', ['title' => 'Iniciativa de entrada', 'organization_id' => $organization->id]);
        $this->assertDatabaseMissing('initiatives', ['title' => 'Iniciativa de entrada', 'organization_id' => $other->id]);
        OrganizationMembership::query()->whereKey($membership->id)->update(['status' => OrganizationMembershipStatus::Suspended]);
        $this->actingAs($user)->withSession(['active_organization_id' => $organization->id])->post(route('initiatives.store'), $this->payload())->assertForbidden();
        $superadmin = User::factory()->create(['global_profile' => GlobalProfile::Administrator]);
        app(OrganizationContext::class)->clear();
        $this->assertThrows(fn () => app(InitiativeConfigurationService::class)->create(collect($this->payload())->except('justification')->all(), $superadmin, 'Inicial'), \LogicException::class);
        app(OrganizationContext::class)->activatePlatformAccess($organization, collect());
        $this->assertInstanceOf(Initiative::class, app(InitiativeConfigurationService::class)->create(collect($this->payload())->except('justification')->all(), $superadmin, 'Inicial'));
    }

    private function actor(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_code' => OrganizationRole::Administrator, 'status' => OrganizationMembershipStatus::Active]);

        return [$organization, $user, $membership];
    }

    private function payload(array $replace = []): array
    {
        return [...['title' => 'Iniciativa de entrada', 'context' => 'Contexto inicial', 'origin' => 'internal', 'execution_nature' => 'internal', 'financial_management_mode' => 'not_applicable', 'management_level' => 'essential', 'methodology' => 'kanban', 'justification' => 'Configuração inicial aprovada.'], ...$replace];
    }
}
