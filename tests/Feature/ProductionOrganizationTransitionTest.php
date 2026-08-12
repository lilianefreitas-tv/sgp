<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class ProductionOrganizationTransitionTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config()->set('database.connections.transition_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('transition_test');
        DB::setDefaultConnection('transition_test');

        $this->createTransitionSchema();
    }

    protected function tearDown(): void
    {
        DB::disconnect('transition_test');
        DB::setDefaultConnection($this->originalConnection);

        parent::tearDown();
    }

    public function test_exact_production_inventory_is_split_between_mppa_and_sgp(): void
    {
        $this->seedApprovedLegacyInventory();

        $this->transitionMigration()->up();
        $this->templateCodeMigration()->up();
        $this->transitionMigration()->up();
        $this->templateCodeMigration()->up();

        $mppaId = (int) DB::table('organizations')->where('slug', 'mppa')->value('id');
        $sgpId = (int) DB::table('organizations')->where('slug', 'sgp')->value('id');

        $this->assertSame(2, DB::table('organizations')->count());
        $this->assertSame(2, DB::table('organization_memberships')->count());
        $this->assertSame([1, 2, 3], DB::table('projects')->where('organization_id', $mppaId)->orderBy('id')->pluck('id')->all());
        $this->assertSame([4], DB::table('projects')->where('organization_id', $sgpId)->pluck('id')->all());
        $this->assertSame([1, 2], DB::table('clients')->where('organization_id', $mppaId)->orderBy('id')->pluck('id')->all());
        $this->assertSame([3], DB::table('clients')->where('organization_id', $sgpId)->pluck('id')->all());
        $this->assertSame(8, DB::table('document_templates')->count());
        $this->assertSame(
            ['MOD-001', 'MOD-002', 'MOD-003', 'MOD-004'],
            DB::table('document_templates')
                ->where('organization_id', $sgpId)
                ->orderBy('code')
                ->pluck('code')
                ->all(),
        );

        foreach ($this->businessTables() as $table) {
            $this->assertSame(0, DB::table($table)->whereNull('organization_id')->count(), $table);
        }

        $templateConflicts = DB::table('project_documents')
            ->join('document_templates', 'project_documents.document_template_id', '=', 'document_templates.id')
            ->whereColumn('project_documents.organization_id', '!=', 'document_templates.organization_id')
            ->count();

        $this->assertSame(0, $templateConflicts);
        $this->assertSame('PRJ-0004', DB::table('projects')->where('id', 4)->value('code'));
    }

    public function test_divergent_inventory_is_rejected_and_transaction_is_rolled_back(): void
    {
        $this->seedApprovedLegacyInventory();
        DB::table('projects')->where('id', 4)->update(['code' => 'PRJ-9999']);

        try {
            $this->transitionMigration()->up();
            $this->fail('A migração deveria rejeitar o código divergente.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('projects#4.code divergente', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('organizations')->count());
        $this->assertSame(0, DB::table('organization_memberships')->count());
        $this->assertSame(4, DB::table('document_templates')->count());
        $this->assertSame(4, DB::table('projects')->whereNull('organization_id')->count());
    }

    public function test_clean_installation_is_not_converted_into_the_production_inventory(): void
    {
        $this->seedTemplates();

        $this->transitionMigration()->up();

        $this->assertSame(0, DB::table('organizations')->count());
        $this->assertSame(4, DB::table('document_templates')->whereNull('organization_id')->count());
    }

    private function transitionMigration(): object
    {
        return require database_path(
            'migrations/2026_08_03_150000_transition_production_organizations.php'
        );
    }

    private function templateCodeMigration(): object
    {
        return require database_path(
            'migrations/2026_08_03_250000_finalize_production_template_codes.php'
        );
    }

    private function createTransitionSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('global_profile');
            $table->boolean('is_active');
        });
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->nullable();
            $table->string('status');
            $table->string('timezone');
            $table->text('settings')->nullable();
            $table->timestamps();
        });
        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role_code');
            $table->string('status');
            $table->boolean('is_default');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
        });
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('manager_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('project_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('requirements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('requirement_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('requirement_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('requirement_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('requirement_id');
            $table->unsignedBigInteger('depends_on_requirement_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('requirement_id')->nullable();
            $table->unsignedBigInteger('parent_task_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('task_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('kanban_boards', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('kanban_columns', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('kanban_board_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('kanban_task_positions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('kanban_column_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->unsignedInteger('version');
            $table->string('header_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        Schema::create('project_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('document_template_id');
            $table->unsignedBigInteger('organization_id')->nullable();
        });

        foreach (['project_comments', 'project_attachments', 'project_activities'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('organization_id')->nullable();
            });
        }
    }

    private function seedApprovedLegacyInventory(): void
    {
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Liliane de Freitas Terra Vieira',
            'global_profile' => 'administrator',
            'is_active' => true,
        ]);
        DB::table('clients')->insert([
            ['id' => 1, 'name' => 'Projeto Colabs/MPPA'],
            ['id' => 2, 'name' => 'Promotoria de Justiça de Altamira'],
            ['id' => 3, 'name' => 'Liliane de Freitas Terra Vieira'],
        ]);
        DB::table('projects')->insert([
            ['id' => 1, 'code' => 'PRJ-0001', 'name' => 'Projeto Atitude – Equidade de Gênero', 'client_id' => 1, 'manager_id' => 1],
            ['id' => 2, 'code' => 'PRJ-0002', 'name' => 'RotaMP – Sistema de Agendamento e Gestão de Motoristas', 'client_id' => 2, 'manager_id' => 1],
            ['id' => 3, 'code' => 'PRJ-0003', 'name' => 'GeoMP – Plataforma de Inteligência Geoespacial', 'client_id' => 2, 'manager_id' => 1],
            ['id' => 4, 'code' => 'PRJ-0004', 'name' => 'SGP - Sistema de Gestão de Projetos de Software', 'client_id' => 3, 'manager_id' => 1],
        ]);

        for ($id = 1; $id <= 4; $id++) {
            DB::table('project_user')->insert(['id' => $id, 'project_id' => $id, 'user_id' => 1]);
        }
        for ($id = 1; $id <= 41; $id++) {
            DB::table('requirements')->insert(['id' => $id, 'project_id' => (($id - 1) % 4) + 1]);
        }
        for ($id = 1; $id <= 9; $id++) {
            DB::table('requirement_versions')->insert(['id' => $id, 'requirement_id' => $id]);
        }
        for ($id = 1; $id <= 25; $id++) {
            DB::table('tasks')->insert([
                'id' => $id,
                'project_id' => (($id - 1) % 4) + 1,
                'requirement_id' => null,
                'parent_task_id' => null,
            ]);
            DB::table('task_histories')->insert(['id' => $id, 'task_id' => $id]);
        }

        DB::table('kanban_boards')->insert([
            ['id' => 1, 'project_id' => 1],
            ['id' => 2, 'project_id' => 4],
        ]);
        for ($id = 1; $id <= 12; $id++) {
            DB::table('kanban_columns')->insert([
                'id' => $id,
                'kanban_board_id' => $id <= 6 ? 1 : 2,
            ]);
        }

        $this->seedTemplates();

        foreach ([1, 2, 3, 4, 1, 4] as $index => $projectId) {
            DB::table('project_documents')->insert([
                'id' => $index + 1,
                'project_id' => $projectId,
                'document_template_id' => ($index % 4) + 1,
            ]);
        }
        for ($id = 1; $id <= 50; $id++) {
            DB::table('project_activities')->insert([
                'id' => $id,
                'project_id' => (($id - 1) % 4) + 1,
            ]);
        }
    }

    private function seedTemplates(): void
    {
        $now = now();

        for ($id = 1; $id <= 4; $id++) {
            DB::table('document_templates')->insert([
                'id' => $id,
                'code' => 'MOD-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
                'created_by' => null,
                'name' => "Modelo {$id}",
                'description' => null,
                'type' => "type_{$id}",
                'version' => 1,
                'header_text' => null,
                'footer_text' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @return list<string> */
    private function businessTables(): array
    {
        return [
            'clients',
            'projects',
            'project_user',
            'requirements',
            'requirement_versions',
            'requirement_dependencies',
            'tasks',
            'task_histories',
            'kanban_boards',
            'kanban_columns',
            'kanban_task_positions',
            'document_templates',
            'project_documents',
            'project_comments',
            'project_attachments',
            'project_activities',
        ];
    }
}
