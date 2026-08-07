<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrganizationBackfillService
{
    /** @var list<string> */
    public const BUSINESS_TABLES = [
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

    /** @var array<string, array{0: string, 1: string}> */
    private const PARENT_RELATIONS = [
        'projects' => ['clients', 'client_id'],
        'project_user' => ['projects', 'project_id'],
        'requirements' => ['projects', 'project_id'],
        'requirement_versions' => ['requirements', 'requirement_id'],
        'requirement_dependencies' => ['requirements', 'requirement_id'],
        'tasks' => ['projects', 'project_id'],
        'task_histories' => ['tasks', 'task_id'],
        'kanban_boards' => ['projects', 'project_id'],
        'kanban_columns' => ['kanban_boards', 'kanban_board_id'],
        'kanban_task_positions' => ['tasks', 'task_id'],
        'project_documents' => ['projects', 'project_id'],
        'project_comments' => ['projects', 'project_id'],
        'project_attachments' => ['projects', 'project_id'],
        'project_activities' => ['projects', 'project_id'],
    ];

    /**
     * @return array{
     *     organization_id: int,
     *     dry_run: bool,
     *     rows: array<string, array{total: int, updated: int, missing: int, invalid: int}>,
     *     conflicts: array<string, int>,
     *     clean: bool
     * }
     */
    public function run(Organization $organization, bool $dryRun = false): array
    {
        DB::beginTransaction();

        try {
            $before = $this->counts();

            $this->fillRootTable('clients', $organization->id);
            $this->fillRootTable('document_templates', $organization->id);

            foreach (self::PARENT_RELATIONS as $table => [$parentTable, $foreignKey]) {
                $this->fillFromParent($table, $parentTable, $foreignKey);
            }

            $after = $this->counts();
            $conflicts = $this->conflicts();
            $rows = [];

            foreach (self::BUSINESS_TABLES as $table) {
                $rows[$table] = [
                    'total' => $after[$table]['total'],
                    'updated' => $before[$table]['missing'] - $after[$table]['missing'],
                    'missing' => $after[$table]['missing'],
                    'invalid' => $after[$table]['invalid'],
                ];
            }

            $clean = collect($rows)->every(
                fn (array $row): bool => $row['missing'] === 0 && $row['invalid'] === 0
            ) && collect($conflicts)->every(fn (int $count): bool => $count === 0);

            $report = [
                'organization_id' => $organization->id,
                'dry_run' => $dryRun,
                'rows' => $rows,
                'conflicts' => $conflicts,
                'clean' => $clean,
            ];

            if ($dryRun || ! $clean) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $report;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    private function fillRootTable(string $table, int $organizationId): void
    {
        DB::table($table)
            ->whereNull('organization_id')
            ->update(['organization_id' => $organizationId]);
    }

    private function fillFromParent(string $table, string $parentTable, string $foreignKey): void
    {
        DB::table($table)
            ->select(['id', $foreignKey])
            ->whereNull('organization_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $parentTable, $foreignKey): void {
                $organizationByParent = DB::table($parentTable)
                    ->whereIn('id', $rows->pluck($foreignKey)->filter()->unique()->values())
                    ->pluck('organization_id', 'id');

                $rows
                    ->groupBy(fn (object $row) => $organizationByParent[$row->{$foreignKey}] ?? null)
                    ->each(function ($group, $organizationId) use ($table): void {
                        if (blank($organizationId)) {
                            return;
                        }

                        DB::table($table)
                            ->whereIn('id', $group->pluck('id'))
                            ->update(['organization_id' => (int) $organizationId]);
                    });
            });
    }

    /** @return array<string, array{total: int, missing: int, invalid: int}> */
    private function counts(): array
    {
        $counts = [];

        foreach (self::BUSINESS_TABLES as $table) {
            $counts[$table] = [
                'total' => DB::table($table)->count(),
                'missing' => DB::table($table)->whereNull('organization_id')->count(),
                'invalid' => DB::table($table)
                    ->leftJoin('organizations', "{$table}.organization_id", '=', 'organizations.id')
                    ->whereNotNull("{$table}.organization_id")
                    ->whereNull('organizations.id')
                    ->count(),
            ];
        }

        return $counts;
    }

    /** @return array<string, int> */
    private function conflicts(): array
    {
        return [
            'projects_clients' => $this->relationConflict('projects', 'clients', 'client_id'),
            'project_user_projects' => $this->relationConflict('project_user', 'projects', 'project_id'),
            'requirements_projects' => $this->relationConflict('requirements', 'projects', 'project_id'),
            'requirement_versions_requirements' => $this->relationConflict('requirement_versions', 'requirements', 'requirement_id'),
            'requirement_dependencies_requirements' => $this->relationConflict('requirement_dependencies', 'requirements', 'requirement_id'),
            'requirement_dependencies_targets' => $this->relationConflict('requirement_dependencies', 'requirements', 'depends_on_requirement_id'),
            'tasks_projects' => $this->relationConflict('tasks', 'projects', 'project_id'),
            'tasks_requirements' => $this->relationConflict('tasks', 'requirements', 'requirement_id', true),
            'tasks_parents' => $this->relationConflict('tasks', 'tasks', 'parent_task_id', true, 'parent_tasks'),
            'task_histories_tasks' => $this->relationConflict('task_histories', 'tasks', 'task_id'),
            'kanban_boards_projects' => $this->relationConflict('kanban_boards', 'projects', 'project_id'),
            'kanban_columns_boards' => $this->relationConflict('kanban_columns', 'kanban_boards', 'kanban_board_id'),
            'kanban_positions_tasks' => $this->relationConflict('kanban_task_positions', 'tasks', 'task_id'),
            'kanban_positions_columns' => $this->relationConflict('kanban_task_positions', 'kanban_columns', 'kanban_column_id'),
            'project_documents_projects' => $this->relationConflict('project_documents', 'projects', 'project_id'),
            'project_documents_templates' => $this->relationConflict('project_documents', 'document_templates', 'document_template_id'),
            'project_comments_projects' => $this->relationConflict('project_comments', 'projects', 'project_id'),
            'project_attachments_projects' => $this->relationConflict('project_attachments', 'projects', 'project_id'),
            'project_activities_projects' => $this->relationConflict('project_activities', 'projects', 'project_id'),
        ];
    }

    private function relationConflict(
        string $childTable,
        string $parentTable,
        string $foreignKey,
        bool $nullable = false,
        ?string $parentAlias = null,
    ): int {
        $alias = $parentAlias ?? $parentTable;
        $parentExpression = $parentAlias ? "{$parentTable} as {$parentAlias}" : $parentTable;

        $query = DB::table($childTable)
            ->join($parentExpression, "{$childTable}.{$foreignKey}", '=', "{$alias}.id")
            ->whereColumn("{$childTable}.organization_id", '!=', "{$alias}.organization_id");

        if ($nullable) {
            $query->whereNotNull("{$childTable}.{$foreignKey}");
        }

        return $query->count();
    }
}
