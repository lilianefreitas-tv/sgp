<?php

namespace Tests\Feature;

use App\Console\Commands\CreateOrganization;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_foundation_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasColumns('organizations', [
            'id', 'name', 'slug', 'type', 'status', 'timezone', 'settings',
        ]));
        $this->assertTrue(Schema::hasColumns('organization_memberships', [
            'id', 'organization_id', 'user_id', 'role_code', 'status', 'is_default', 'joined_at',
        ]));
    }

    public function test_organization_attributes_are_cast_to_domain_types(): void
    {
        $organization = Organization::factory()->create([
            'type' => OrganizationType::PublicBody,
            'status' => OrganizationStatus::Active,
            'settings' => ['locale' => 'pt_BR'],
        ]);

        $this->assertSame(OrganizationType::PublicBody, $organization->type);
        $this->assertSame(OrganizationStatus::Active, $organization->status);
        $this->assertSame(['locale' => 'pt_BR'], $organization->settings);
        $this->assertTrue($organization->isActive());
    }

    public function test_global_user_can_belong_to_multiple_organizations(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();

        OrganizationMembership::factory()->for($user)->for($first)->owner()->create();
        OrganizationMembership::factory()->for($user)->for($second)->create([
            'role_code' => OrganizationRole::Reader,
        ]);

        $this->assertCount(2, $user->fresh()->organizations);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $second->id,
            'user_id' => $user->id,
            'role_code' => OrganizationRole::Reader->value,
        ]);
    }

    public function test_membership_is_unique_for_each_organization_and_user(): void
    {
        $membership = OrganizationMembership::factory()->create();

        $this->expectException(QueryException::class);

        OrganizationMembership::factory()->create([
            'organization_id' => $membership->organization_id,
            'user_id' => $membership->user_id,
        ]);
    }

    public function test_platform_administrator_can_adopt_initial_organization_by_command(): void
    {
        $administrator = User::factory()->administrator()->create([
            'email' => 'admin@sgp.test',
        ]);

        $this->artisan('sgp:create-organization', [
            '--name' => 'SGP Demonstração',
            '--slug' => 'sgp-demonstracao',
            '--type' => OrganizationType::Company->value,
            '--timezone' => 'America/Belem',
            '--owner-email' => $administrator->email,
        ])->assertSuccessful();

        $organization = Organization::query()->where('slug', 'sgp-demonstracao')->firstOrFail();

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseMissing('organizations', ['slug' => CreateOrganization::BOOTSTRAP_SLUG]);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $administrator->id,
            'role_code' => OrganizationRole::Owner->value,
            'status' => OrganizationMembershipStatus::Active->value,
            'is_default' => true,
        ]);
    }

    public function test_regular_user_cannot_be_initial_owner_created_by_command(): void
    {
        $user = User::factory()->create(['email' => 'user@sgp.test']);

        $this->artisan('sgp:create-organization', [
            '--name' => 'Organização Inválida',
            '--owner-email' => $user->email,
        ])->assertFailed();

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseHas('organizations', ['slug' => CreateOrganization::BOOTSTRAP_SLUG]);
    }
}
