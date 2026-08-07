<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyProductionTransition extends Command
{
    protected $signature = 'sgp:verify-production-transition';

    protected $description = 'Valida o resultado final da transição organizacional controlada da produção';

    /** @var array<string, int> */
    private const EXPECTED_COUNTS = [
        'clients' => 3,
        'projects' => 4,
        'project_user' => 4,
        'requirements' => 41,
        'requirement_versions' => 9,
        'requirement_dependencies' => 0,
        'tasks' => 25,
        'task_histories' => 25,
        'kanban_boards' => 2,
        'kanban_columns' => 12,
        'kanban_task_positions' => 0,
        'document_templates' => 8,
        'project_documents' => 6,
        'project_comments' => 0,
        'project_attachments' => 0,
        'project_activities' => 50,
    ];

    /** @var list<array{string, string, string}> */
    private const RELATIONS = [
        ['projects', 'client_id', 'clients'],
        ['project_user', 'project_id', 'projects'],
        ['requirements', 'project_id', 'projects'],
        ['requirement_versions', 'requirement_id', 'requirements'],
        ['requirement_dependencies', 'requirement_id', 'requirements'],
        ['requirement_dependencies', 'depends_on_requirement_id', 'requirements'],
        ['tasks', 'project_id', 'projects'],
        ['tasks', 'requirement_id', 'requirements'],
        ['tasks', 'parent_task_id', 'tasks'],
        ['task_histories', 'task_id', 'tasks'],
        ['kanban_boards', 'project_id', 'projects'],
        ['kanban_columns', 'kanban_board_id', 'kanban_boards'],
        ['kanban_task_positions', 'task_id', 'tasks'],
        ['kanban_task_positions', 'kanban_column_id', 'kanban_columns'],
        ['project_documents', 'project_id', 'projects'],
        ['project_documents', 'document_template_id', 'document_templates'],
        ['project_comments', 'project_id', 'projects'],
        ['project_attachments', 'project_id', 'projects'],
        ['project_activities', 'project_id', 'projects'],
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('organizations')
            || ! Schema::hasColumn('projects', 'organization_id')
            || ! Schema::hasColumn('organizations', 'next_project_number')) {
            $this->components->error('A estrutura organizacional final ainda não foi aplicada.');

            return self::FAILURE;
        }

        $problems = [];
        $mppa = DB::table('organizations')->where('slug', 'mppa')->first();
        $sgp = DB::table('organizations')->where('slug', 'sgp')->first();

        if ($mppa === null || $sgp === null || DB::table('organizations')->count() !== 2) {
            $problems[] = 'O banco deve possuir somente as organizações mppa e sgp.';
        }

        if ($mppa !== null && (int) $mppa->next_project_number !== 4) {
            $problems[] = 'A próxima sequência de projetos do MPPA deve ser 4.';
        }

        if ($sgp !== null && (int) $sgp->next_project_number !== 2) {
            $problems[] = 'A próxima sequência de projetos do SGP deve ser 2.';
        }

        $expectedProjects = [
            1 => ['slug' => 'mppa', 'code' => 'PRJ-0001'],
            2 => ['slug' => 'mppa', 'code' => 'PRJ-0002'],
            3 => ['slug' => 'mppa', 'code' => 'PRJ-0003'],
            4 => ['slug' => 'sgp', 'code' => 'PRJ-0001'],
        ];
        $projects = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->orderBy('projects.id')
            ->get(['projects.id', 'projects.code', 'organizations.slug']);

        foreach ($expectedProjects as $id => $expected) {
            $project = $projects->firstWhere('id', $id);

            if ($project === null
                || $project->slug !== $expected['slug']
                || $project->code !== $expected['code']) {
                $problems[] = "Projeto {$id} não corresponde a {$expected['slug']}/{$expected['code']}.";
            }
        }

        $counts = [];
        $missingOrganizations = [];

        foreach (self::EXPECTED_COUNTS as $table => $expected) {
            $counts[$table] = DB::table($table)->count();
            $missingOrganizations[$table] = DB::table($table)->whereNull('organization_id')->count();

            if ($counts[$table] !== $expected) {
                $problems[] = "{$table}: esperado {$expected}; encontrado {$counts[$table]}.";
            }

            if ($missingOrganizations[$table] > 0) {
                $problems[] = "{$table}: {$missingOrganizations[$table]} registro(s) sem organização.";
            }
        }

        $membershipCount = DB::table('organization_memberships')
            ->where('user_id', 1)
            ->where('role_code', 'owner')
            ->where('status', 'active')
            ->count();

        if ($membershipCount !== 2) {
            $problems[] = 'A usuária 1 deve ser Administradora principal ativa nas duas organizações.';
        }

        $relationConflicts = [];

        foreach (self::RELATIONS as $index => [$child, $foreignKey, $parent]) {
            $alias = "verify_parent_{$index}";
            $key = "{$child}.{$foreignKey}";
            $relationConflicts[$key] = DB::table($child)
                ->join("{$parent} as {$alias}", "{$child}.{$foreignKey}", '=', "{$alias}.id")
                ->whereNotNull("{$child}.{$foreignKey}")
                ->whereColumn("{$child}.organization_id", '!=', "{$alias}.organization_id")
                ->count();

            if ($relationConflicts[$key] > 0) {
                $problems[] = "{$key}: {$relationConflicts[$key]} conflito(s) organizacional(is).";
            }
        }

        $report = [
            'clean' => $problems === [],
            'organizations' => [
                'mppa' => $mppa?->id,
                'sgp' => $sgp?->id,
            ],
            'projects' => $projects,
            'counts' => $counts,
            'missing_organization' => $missingOrganizations,
            'relation_conflicts' => $relationConflicts,
            'problems' => $problems,
        ];

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        if ($problems !== []) {
            $this->components->error('A validação final encontrou pendências.');

            return self::FAILURE;
        }

        $this->components->info('Transição organizacional validada sem pendências.');

        return self::SUCCESS;
    }
}
