<?php

namespace Tests\Feature;

use App\Console\Commands\CreateOrganization;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_installation_has_initial_organization_and_owned_templates(): void
    {
        $organization = Organization::query()
            ->where('slug', CreateOrganization::BOOTSTRAP_SLUG)
            ->firstOrFail();

        $this->assertSame(4, DB::table('document_templates')->count());
        $this->assertSame(0, DB::table('document_templates')->whereNull('organization_id')->count());
        $this->assertSame(4, DB::table('document_templates')->where('organization_id', $organization->id)->count());
    }

    public function test_organization_is_required_on_business_tables(): void
    {
        $this->expectException(QueryException::class);

        DB::table('clients')->insert([
            'name' => 'Cliente sem organização',
            'type' => 'unit',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unknown_organization_is_rejected_by_foreign_key(): void
    {
        $this->expectException(QueryException::class);

        DB::table('clients')->insert([
            'organization_id' => 999999,
            'name' => 'Cliente inválido',
            'type' => 'unit',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_project_code_is_unique_inside_organization_but_reusable_between_organizations(): void
    {
        $first = $this->createProjectIn(Organization::query()->firstOrFail(), 'PRJ-IGUAL');
        $secondOrganization = Organization::factory()->create();
        $second = $this->createProjectIn($secondOrganization, 'PRJ-IGUAL');

        $this->assertNotSame($first->organization_id, $second->organization_id);

        $this->expectException(QueryException::class);
        $this->createProjectIn($secondOrganization, 'PRJ-IGUAL');
    }

    public function test_automatic_project_sequence_is_independent_for_each_organization(): void
    {
        $firstOrganization = Organization::query()->firstOrFail();
        $secondOrganization = Organization::factory()->create();
        $firstClient = Client::factory()->create(['organization_id' => $firstOrganization->id]);
        $secondClient = Client::factory()->create(['organization_id' => $secondOrganization->id]);

        $firstA = Project::factory()->create([
            'organization_id' => $firstOrganization->id,
            'client_id' => $firstClient->id,
            'code' => null,
        ]);
        $firstB = Project::factory()->create([
            'organization_id' => $secondOrganization->id,
            'client_id' => $secondClient->id,
            'code' => null,
        ]);
        $secondA = Project::factory()->create([
            'organization_id' => $firstOrganization->id,
            'client_id' => $firstClient->id,
            'code' => null,
        ]);

        $this->assertSame('PRJ-0001', $firstA->code);
        $this->assertSame('PRJ-0001', $firstB->code);
        $this->assertSame('PRJ-0002', $secondA->code);
    }

    public function test_project_cannot_reference_client_from_another_organization(): void
    {
        $firstOrganization = Organization::query()->firstOrFail();
        $secondOrganization = Organization::factory()->create();
        $foreignClient = Client::factory()->create(['organization_id' => $firstOrganization->id]);

        $this->expectException(QueryException::class);

        Project::factory()->create([
            'organization_id' => $secondOrganization->id,
            'client_id' => $foreignClient->id,
        ]);
    }

    public function test_requirement_cannot_reference_project_from_another_organization(): void
    {
        $firstOrganization = Organization::query()->firstOrFail();
        $secondOrganization = Organization::factory()->create();
        $project = $this->createProjectIn($firstOrganization, 'PRJ-ORIGEM');

        $this->expectException(QueryException::class);

        Requirement::factory()->create([
            'organization_id' => $secondOrganization->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_organization_is_propagated_from_root_to_descendants(): void
    {
        $organization = Organization::query()->firstOrFail();
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);
        $dependency = Requirement::factory()->create(['project_id' => $project->id]);

        $requirement->dependencies()->attach($dependency->id);

        $this->assertSame($organization->id, $client->organization_id);
        $this->assertSame($organization->id, $project->organization_id);
        $this->assertSame($organization->id, $requirement->organization_id);
        $this->assertDatabaseHas('requirement_dependencies', [
            'organization_id' => $organization->id,
            'requirement_id' => $requirement->id,
            'depends_on_requirement_id' => $dependency->id,
        ]);
    }

    public function test_f4_migration_can_be_rolled_back_and_applied_again(): void
    {
        $f4 = require database_path('migrations/2026_08_03_200000_enforce_organization_integrity.php');
        $p01 = require database_path('migrations/2026_08_12_000000_create_initiative_foundation.php');
        $p012 = require database_path('migrations/2026_08_12_100000_create_applicability_foundation.php');

        $this->assertTrue(Schema::hasTable('project_configuration_versions'));
        $p012->down();
        $p01->down();

        try {
            $f4->down();
            $this->assertFalse(Schema::hasTable('initiative_configuration_versions'));

            $id = DB::table('clients')->insertGetId([
                'organization_id' => null,
                'name' => 'Cliente temporário',
                'type' => 'unit',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('clients')->where('id', $id)->delete();
        } finally {
            $f4->up();
            $p01->up();
            $p012->up();
        }

        $this->assertTrue(Schema::hasTable('initiatives'));
        $this->assertTrue(Schema::hasTable('initiative_configuration_versions'));
        $this->assertTrue(Schema::hasTable('project_configuration_versions'));
        $this->assertTrue(Schema::hasTable('applicability_decisions'));
        $this->assertTrue(collect(Schema::getIndexes('projects'))->contains('name', 'projects_id_org_unique'));
        $this->assertTrue(collect(Schema::getForeignKeys('project_configuration_versions'))
            ->contains(fn (array $foreignKey) => $foreignKey['columns'] === ['project_id', 'organization_id']
                && $foreignKey['foreign_table'] === 'projects'
                && $foreignKey['foreign_columns'] === ['id', 'organization_id']));

        $this->expectException(QueryException::class);
        DB::table('clients')->insert([
            'organization_id' => null,
            'name' => 'Cliente sem organização após reaplicação',
            'type' => 'unit',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProjectIn(Organization $organization, string $code): Project
    {
        $client = Client::factory()->create(['organization_id' => $organization->id]);

        return Project::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'code' => $code,
        ]);
    }
}
