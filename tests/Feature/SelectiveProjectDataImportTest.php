<?php

namespace Tests\Feature;

use App\Enums\GlobalProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SelectiveProjectDataImportTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceDirectory;

    private User $owner;

    /** @var array<string, int> */
    private array $organizations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDirectory = storage_path('framework/testing/selective-import-'.uniqid());
        File::makeDirectory($this->sourceDirectory, 0755, true);

        $this->owner = User::factory()->create([
            'email' => 'lili@example.test',
            'global_profile' => GlobalProfile::Administrator,
            'is_active' => true,
        ]);
        $this->organizations = [
            'mppa' => $this->createOrganization('MPPA', 'mppa'),
            'sgp' => $this->createOrganization('SGP', 'sgp'),
        ];

        foreach ($this->organizations as $index => $organizationId) {
            DB::table('organization_memberships')->insert([
                'organization_id' => $organizationId,
                'user_id' => $this->owner->id,
                'role_code' => 'owner',
                'status' => 'active',
                'is_default' => $index === 'mppa',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->writeApprovedSource();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceDirectory);

        parent::tearDown();
    }

    public function test_dry_run_validates_everything_without_writing_rows(): void
    {
        $reportPath = $this->sourceDirectory.'/dry-run-report.json';
        $exit = $this->runImport(reportPath: $reportPath);

        $this->assertSame(0, $exit, Artisan::output());
        $report = json_decode(File::get($reportPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'projects' => 4,
            'requirements' => 41,
            'requirement_versions' => 9,
            'requirement_dependencies' => 0,
            'tasks' => 25,
            'task_histories' => 25,
        ], $report['counts']);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('requirements', 0);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_apply_preserves_ids_and_relations_while_remapping_tenant_and_user(): void
    {
        $exit = $this->runImport(true);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertDatabaseCount('projects', 4);
        $this->assertDatabaseCount('requirements', 41);
        $this->assertDatabaseCount('requirement_versions', 9);
        $this->assertDatabaseCount('requirement_dependencies', 0);
        $this->assertDatabaseCount('tasks', 25);
        $this->assertDatabaseCount('task_histories', 25);

        $this->assertDatabaseHas('projects', [
            'id' => 4,
            'code' => 'PRJ-0001',
            'organization_id' => $this->organizations['sgp'],
            'client_id' => null,
            'manager_id' => $this->owner->id,
            'management_level' => 'essential',
        ]);
        $this->assertDatabaseHas('projects', [
            'id' => 3,
            'code' => 'PRJ-0003',
            'organization_id' => $this->organizations['mppa'],
            'client_id' => null,
            'manager_id' => $this->owner->id,
        ]);
        $this->assertDatabaseHas('requirements', [
            'id' => 34,
            'project_id' => 4,
            'organization_id' => $this->organizations['sgp'],
            'responsible_id' => null,
        ]);
        $this->assertDatabaseHas('requirements', [
            'id' => 33,
            'project_id' => 3,
            'organization_id' => $this->organizations['mppa'],
            'responsible_id' => $this->owner->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => 2,
            'parent_task_id' => 1,
            'organization_id' => $this->organizations['mppa'],
        ]);
        $this->assertDatabaseHas('task_histories', [
            'id' => 25,
            'changed_by' => $this->owner->id,
            'organization_id' => $this->organizations['sgp'],
        ]);
        $this->assertSame(4, (int) DB::table('organizations')->where('id', $this->organizations['mppa'])->value('next_project_number'));
        $this->assertSame(2, (int) DB::table('organizations')->where('id', $this->organizations['sgp'])->value('next_project_number'));
    }

    public function test_tampered_csv_is_rejected_before_any_write(): void
    {
        File::append($this->sourceDirectory.'/tasks.csv', "\n");

        $exit = $this->runImport(true);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('SHA-256 de tasks.csv diverge', Artisan::output());
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_non_empty_target_is_rejected(): void
    {
        DB::table('projects')->insert([
            'organization_id' => $this->organizations['mppa'],
            'code' => 'PRJ-9999',
            'client_id' => null,
            'manager_id' => $this->owner->id,
            'name' => 'Registro existente',
            'objective' => 'Bloquear importação sobre banco ocupado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exit = $this->runImport(true);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('já possui dados', Artisan::output());
        $this->assertDatabaseCount('projects', 1);
    }

    private function runImport(bool $apply = false, ?string $reportPath = null): int
    {
        $arguments = [
            'directory' => $this->sourceDirectory,
            '--owner-email' => $this->owner->email,
            '--no-interaction' => true,
        ];

        if ($apply) {
            $arguments['--apply'] = true;
        }

        if ($reportPath !== null) {
            $arguments['--report'] = $reportPath;
        }

        return Artisan::call('sgp:import-selective-project-data', $arguments);
    }

    private function createOrganization(string $name, string $slug): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'type' => 'team',
            'status' => 'active',
            'timezone' => 'America/Belem',
            'settings' => json_encode([], JSON_THROW_ON_ERROR),
            'next_project_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function writeApprovedSource(): void
    {
        $headers = $this->headers();
        $rows = [
            'projects' => $this->projectRows(),
            'requirements' => $this->requirementRows(),
            'requirement_versions' => $this->requirementVersionRows(),
            'requirement_dependencies' => [],
            'tasks' => $this->taskRows(),
            'task_histories' => $this->taskHistoryRows(),
        ];

        foreach ($headers as $table => $tableHeaders) {
            $this->writeCsv($this->sourceDirectory."/{$table}.csv", $tableHeaders, $rows[$table]);
        }

        $manifestRows = [];

        foreach ($rows as $table => $tableRows) {
            $manifestRows[] = [
                $table,
                (string) count($tableRows),
                (string) count($tableRows),
                'OK',
                strtoupper((string) hash_file('sha256', $this->sourceDirectory."/{$table}.csv")),
            ];
        }

        $this->writeCsv(
            $this->sourceDirectory.'/manifest-sha256.csv',
            ['Tabela', 'Esperados', 'Exportados', 'Status', 'SHA256'],
            $manifestRows,
        );
    }

    /** @return array<string, list<string>> */
    private function headers(): array
    {
        return [
            'projects' => [
                'id', 'code', 'client_id', 'manager_id', 'name', 'description', 'objective',
                'justification', 'management_level', 'methodology', 'status', 'start_date',
                'expected_end_date', 'end_date', 'is_active', 'archived_at', 'created_at',
                'updated_at', 'document_context', 'problem_statement', 'solution_summary',
                'target_audience', 'scope_included', 'scope_excluded', 'assumptions',
                'constraints', 'success_criteria', 'future_vision',
            ],
            'requirements' => [
                'id', 'project_id', 'responsible_id', 'code', 'title', 'description', 'type',
                'priority', 'status', 'acceptance_criteria', 'source', 'current_version',
                'is_active', 'created_at', 'updated_at',
            ],
            'requirement_versions' => [
                'id', 'requirement_id', 'version_number', 'title', 'description',
                'acceptance_criteria', 'changed_by', 'change_reason', 'created_at',
            ],
            'requirement_dependencies' => [
                'id', 'requirement_id', 'depends_on_requirement_id', 'created_at', 'updated_at',
            ],
            'tasks' => [
                'id', 'project_id', 'requirement_id', 'responsible_id', 'parent_task_id', 'code',
                'title', 'description', 'priority', 'status', 'estimated_hours', 'start_date',
                'due_date', 'completed_at', 'is_active', 'created_at', 'updated_at',
            ],
            'task_histories' => [
                'id', 'task_id', 'changed_by', 'event', 'from_status', 'to_status',
                'changed_fields', 'notes', 'created_at',
            ],
        ];
    }

    /** @return list<list<string|null>> */
    private function projectRows(): array
    {
        $rows = [];

        foreach (range(1, 4) as $id) {
            $rows[] = [
                (string) $id,
                'PRJ-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT),
                (string) min($id, 3),
                '1',
                "Projeto {$id}",
                $id === 1 ? "Descrição com\nquebra de linha" : null,
                "Objetivo {$id}",
                null,
                'simplified',
                null,
                'planning',
                null,
                null,
                null,
                't',
                null,
                '2026-08-05 10:00:00',
                '2026-08-05 10:00:00',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ];
        }

        return $rows;
    }

    /** @return list<list<string|null>> */
    private function requirementRows(): array
    {
        $rows = [];
        $sequence = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        foreach (range(1, 41) as $id) {
            $projectId = min(4, intdiv($id - 1, 11) + 1);
            $sequence[$projectId]++;
            $rows[] = [
                (string) $id,
                (string) $projectId,
                $id % 2 === 0 ? null : '1',
                'REQ-'.str_pad((string) $sequence[$projectId], 3, '0', STR_PAD_LEFT),
                "Requisito {$id}",
                null,
                'functional',
                'medium',
                'proposed',
                "Critério {$id}",
                null,
                '1',
                't',
                '2026-08-05 10:00:00',
                '2026-08-05 10:00:00',
            ];
        }

        return $rows;
    }

    /** @return list<list<string|null>> */
    private function requirementVersionRows(): array
    {
        $rows = [];

        foreach (range(1, 9) as $id) {
            $rows[] = [
                (string) $id,
                (string) $id,
                '1',
                "Requisito {$id}",
                null,
                "Critério {$id}",
                '1',
                'Versão inicial',
                '2026-08-05 10:00:00',
            ];
        }

        return $rows;
    }

    /** @return list<list<string|null>> */
    private function taskRows(): array
    {
        $rows = [];
        $sequence = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $firstRequirement = [1 => 1, 2 => 12, 3 => 23, 4 => 34];

        foreach (range(1, 25) as $id) {
            $projectId = min(4, intdiv($id - 1, 7) + 1);
            $sequence[$projectId]++;
            $rows[] = [
                (string) $id,
                (string) $projectId,
                (string) $firstRequirement[$projectId],
                $id % 2 === 0 ? null : '1',
                $id === 2 ? '1' : null,
                'TAR-'.str_pad((string) $sequence[$projectId], 3, '0', STR_PAD_LEFT),
                "Tarefa {$id}",
                null,
                'medium',
                'backlog',
                '1.50',
                null,
                null,
                null,
                't',
                '2026-08-05 10:00:00',
                '2026-08-05 10:00:00',
            ];
        }

        return $rows;
    }

    /** @return list<list<string|null>> */
    private function taskHistoryRows(): array
    {
        $rows = [];

        foreach (range(1, 25) as $id) {
            $rows[] = [
                (string) $id,
                (string) $id,
                '1',
                'created',
                null,
                'backlog',
                json_encode(['title' => "Tarefa {$id}"], JSON_THROW_ON_ERROR),
                null,
                '2026-08-05 10:00:00',
            ];
        }

        return $rows;
    }

    /** @param list<string> $headers @param list<list<string|null>> $rows */
    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'wb');
        $this->assertNotFalse($handle);
        fputcsv($handle, $headers, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        fclose($handle);
    }
}
