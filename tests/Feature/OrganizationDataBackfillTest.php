<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Services\OrganizationBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationDataBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_business_tables_receive_nullable_organization_column(): void
    {
        foreach (OrganizationBackfillService::BUSINESS_TABLES as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'organization_id'),
                "A tabela {$table} não possui organization_id.",
            );
        }
    }

    public function test_command_backfills_and_reconciles_all_legacy_business_tables(): void
    {
        $organization = Organization::factory()->create(['slug' => 'organizacao-inicial']);
        $this->createCompleteLegacyGraph();

        $this->artisan('sgp:backfill-organization', [
            'organization' => $organization->slug,
            '--force' => true,
        ])->assertSuccessful();

        foreach (OrganizationBackfillService::BUSINESS_TABLES as $table) {
            $this->assertSame(0, DB::table($table)->whereNull('organization_id')->count(), $table);
            $this->assertSame(
                DB::table($table)->count(),
                DB::table($table)->where('organization_id', $organization->id)->count(),
                $table,
            );
        }
    }

    public function test_dry_run_reconciles_but_does_not_persist_changes(): void
    {
        $organization = Organization::factory()->create(['slug' => 'organizacao-inicial']);
        $client = Client::factory()->create();

        $this->artisan('sgp:backfill-organization', [
            'organization' => $organization->slug,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertNull($client->fresh()->organization_id);
    }

    public function test_existing_valid_organization_is_preserved_and_inherited_by_children(): void
    {
        $initial = Organization::factory()->create(['slug' => 'organizacao-inicial']);
        $existing = Organization::factory()->create(['slug' => 'organizacao-existente']);
        $client = Client::factory()->create();
        $manager = User::factory()->create();
        $project = Project::factory()->for($client)->for($manager, 'manager')->create();

        DB::table('clients')->where('id', $client->id)->update(['organization_id' => $existing->id]);

        $this->artisan('sgp:backfill-organization', [
            'organization' => $initial->slug,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame($existing->id, (int) $client->fresh()->organization_id);
        $this->assertSame($existing->id, (int) $project->fresh()->organization_id);
    }

    public function test_conflicting_parent_and_child_organizations_fail_and_rollback(): void
    {
        $first = Organization::factory()->create(['slug' => 'organizacao-a']);
        $second = Organization::factory()->create(['slug' => 'organizacao-b']);
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();

        DB::table('clients')->where('id', $client->id)->update(['organization_id' => $first->id]);
        DB::table('projects')->where('id', $project->id)->update(['organization_id' => $second->id]);

        $this->artisan('sgp:backfill-organization', [
            'organization' => $first->slug,
            '--force' => true,
        ])->assertFailed();

        $this->assertSame($second->id, (int) $project->fresh()->organization_id);
    }

    public function test_unknown_organization_fails_without_changing_data(): void
    {
        $client = Client::factory()->create();

        $this->artisan('sgp:backfill-organization', [
            'organization' => 'nao-existe',
            '--force' => true,
        ])->assertFailed();

        $this->assertNull($client->fresh()->organization_id);
    }

    private function createCompleteLegacyGraph(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->for($user, 'manager')->create();

        DB::table('project_user')->insert([
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
            'requirement_id' => $requirement->id,
            'version_number' => 1,
            'title' => $requirement->title,
            'changed_by' => $user->id,
            'created_at' => now(),
        ]);
        DB::table('requirement_dependencies')->insert([
            'requirement_id' => $requirement->id,
            'depends_on_requirement_id' => $dependency->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $task = Task::factory()->for($project)->create(['requirement_id' => $requirement->id]);
        DB::table('task_histories')->insert([
            'task_id' => $task->id,
            'changed_by' => $user->id,
            'event' => 'created',
            'created_at' => now(),
        ]);

        $boardId = DB::table('kanban_boards')->insertGetId([
            'project_id' => $project->id,
            'name' => 'Quadro Kanban',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $columnId = DB::table('kanban_columns')->insertGetId([
            'kanban_board_id' => $boardId,
            'status' => 'backlog',
            'name' => 'Backlog',
            'position' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('kanban_task_positions')->insert([
            'task_id' => $task->id,
            'kanban_column_id' => $columnId,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $template = DB::table('document_templates')->first();
        DB::table('project_documents')->insert([
            'project_id' => $project->id,
            'document_template_id' => $template->id,
            'generated_by' => $user->id,
            'type' => $template->type,
            'title' => 'Documento legado',
            'version' => 1,
            'docx_path' => 'documents/legacy.docx',
            'pdf_path' => 'documents/legacy.pdf',
            'docx_file_name' => 'legacy.docx',
            'pdf_file_name' => 'legacy.pdf',
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_comments')->insert([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'body' => 'Comentário legado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_attachments')->insert([
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'context_type' => 'project',
            'context_id' => $project->id,
            'disk' => 'local',
            'path' => 'attachments/legacy.txt',
            'original_name' => 'legacy.txt',
            'size_bytes' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'event_type' => 'created',
            'description' => 'Atividade legada',
            'created_at' => now(),
        ]);
    }
}
