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

    public function test_current_schema_preserves_f4_composite_integrity_constraints(): void
    {
        // A F4 must roll back only through Laravel's reverse migration chain.
        $this->assertCompositeForeignKey('projects', ['client_id', 'organization_id'], 'clients', 'projects_client_org_fk');
        $this->assertCompositeForeignKey('requirements', ['project_id', 'organization_id'], 'projects', 'requirements_project_org_fk');
        $this->assertCompositeForeignKey('requirement_dependencies', ['requirement_id', 'organization_id'], 'requirements', 'req_deps_requirement_org_fk');
        $this->assertTrue(collect(Schema::getIndexes('projects'))->contains('name', 'projects_id_org_unique'));

    }

    private function assertCompositeForeignKey(string $table, array $columns, string $foreignTable, string $name): void
    {
        $foreignKey = collect(Schema::getForeignKeys($table))->first(fn (array $foreignKey) => $foreignKey['columns'] === $columns
            && $foreignKey['foreign_table'] === $foreignTable
            && $foreignKey['foreign_columns'] === ['id', 'organization_id']);

        $this->assertNotNull($foreignKey);
        if (DB::getDriverName() === 'pgsql') {
            $this->assertSame($name, $foreignKey['name']);
        }
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
