<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Services\OrganizationBackfillService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationDataBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_business_tables_keep_required_organization_column(): void
    {
        foreach (OrganizationBackfillService::BUSINESS_TABLES as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'organization_id'),
                "A tabela {$table} não possui organization_id.",
            );
            $this->assertSame(0, DB::table($table)->whereNull('organization_id')->count(), $table);
        }
    }

    public function test_command_is_idempotent_after_f4_integrity_is_active(): void
    {
        $organization = Organization::query()->firstOrFail();
        $this->createCompleteGraph($organization);

        $this->artisan('sgp:backfill-organization', [
            'organization' => $organization->slug,
            '--force' => true,
        ])->assertSuccessful();

        foreach (OrganizationBackfillService::BUSINESS_TABLES as $table) {
            $this->assertSame(0, DB::table($table)->whereNull('organization_id')->count(), $table);
        }
    }

    public function test_dry_run_does_not_change_existing_organization(): void
    {
        $organization = Organization::query()->firstOrFail();
        $client = Client::factory()->create(['organization_id' => $organization->id]);

        $this->artisan('sgp:backfill-organization', [
            'organization' => $organization->slug,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame($organization->id, (int) $client->fresh()->organization_id);
    }

    public function test_existing_valid_organization_is_preserved_and_inherited_by_children(): void
    {
        $initial = Organization::query()->firstOrFail();
        $existing = Organization::factory()->create(['slug' => 'organizacao-existente']);
        $client = Client::factory()->create(['organization_id' => $existing->id]);
        $manager = User::factory()->create();
        $project = Project::factory()->for($client)->for($manager, 'manager')->create();

        $this->artisan('sgp:backfill-organization', [
            'organization' => $initial->slug,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame($existing->id, (int) $client->fresh()->organization_id);
        $this->assertSame($existing->id, (int) $project->fresh()->organization_id);
    }

    public function test_conflicting_parent_and_child_organizations_are_rejected_by_database(): void
    {
        $first = Organization::query()->firstOrFail();
        $second = Organization::factory()->create(['slug' => 'organizacao-b']);
        $client = Client::factory()->create(['organization_id' => $first->id]);

        $this->expectException(QueryException::class);

        Project::factory()->for($client)->create(['organization_id' => $second->id]);
    }

    public function test_unknown_organization_fails_without_changing_data(): void
    {
        $organization = Organization::query()->firstOrFail();
        $client = Client::factory()->create(['organization_id' => $organization->id]);

        $this->artisan('sgp:backfill-organization', [
            'organization' => 'nao-existe',
            '--force' => true,
        ])->assertFailed();

        $this->assertSame($organization->id, (int) $client->fresh()->organization_id);
    }

    private function createCompleteGraph(Organization $organization): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['organization_id' => $organization->id]);
        $project = Project::factory()->for($client)->for($user, 'manager')->create();

        DB::table('project_user')->insert([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => 'observer',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requirement = Requirement::factory()->for($project)->create();
        $dependency = Requirement::factory()->for($project)->create();

        DB::table('requirement_versions')->insert([
            'organization_id' => $organization->id,
            'requirement_id' => $requirement->id,
            'version_number' => 1,
            'title' => $requirement->title,
            'changed_by' => $user->id,
            'created_at' => now(),
        ]);
        DB::table('requirement_dependencies')->insert([
            'organization_id' => $organization->id,
            'requirement_id' => $requirement->id,
            'depends_on_requirement_id' => $dependency->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $task = Task::factory()->for($project)->create(['requirement_id' => $requirement->id]);
        DB::table('task_histories')->insert([
            'organization_id' => $organization->id,
            'task_id' => $task->id,
            'changed_by' => $user->id,
            'event' => 'created',
            'created_at' => now(),
        ]);

        $boardId = DB::table('kanban_boards')->insertGetId([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'name' => 'Quadro Kanban',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $columnId = DB::table('kanban_columns')->insertGetId([
            'organization_id' => $organization->id,
            'kanban_board_id' => $boardId,
            'status' => 'backlog',
            'name' => 'Backlog',
            'position' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('kanban_task_positions')->insert([
            'organization_id' => $organization->id,
            'task_id' => $task->id,
            'kanban_column_id' => $columnId,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $template = DB::table('document_templates')->where('organization_id', $organization->id)->first();
        DB::table('project_documents')->insert([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'document_template_id' => $template->id,
            'generated_by' => $user->id,
            'type' => $template->type,
            'title' => 'Documento vigente',
            'version' => 1,
            'docx_path' => 'documents/current.docx',
            'pdf_path' => 'documents/current.pdf',
            'docx_file_name' => 'current.docx',
            'pdf_file_name' => 'current.pdf',
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_comments')->insert([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'body' => 'Comentário vigente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_attachments')->insert([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'disk' => 'local',
            'path' => 'attachments/current.txt',
            'original_name' => 'current.txt',
            'size_bytes' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_activities')->insert([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'event_type' => 'created',
            'description' => 'Atividade vigente',
            'created_at' => now(),
        ]);
    }
}
