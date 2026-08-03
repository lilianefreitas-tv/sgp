<?php

use App\Console\Commands\CreateOrganization;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
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

    /** @var list<string> */
    private array $anchorTables = [
        'clients',
        'projects',
        'requirements',
        'tasks',
        'kanban_boards',
        'kanban_columns',
        'document_templates',
    ];

    /** @var list<array{string, string, string, string, string}> */
    private array $relations = [
        ['projects', 'client_id', 'clients', 'projects_client_org_fk', 'restrict'],
        ['project_user', 'project_id', 'projects', 'project_user_project_org_fk', 'cascade'],
        ['requirements', 'project_id', 'projects', 'requirements_project_org_fk', 'cascade'],
        ['requirement_versions', 'requirement_id', 'requirements', 'req_versions_requirement_org_fk', 'cascade'],
        ['requirement_dependencies', 'requirement_id', 'requirements', 'req_deps_requirement_org_fk', 'cascade'],
        ['requirement_dependencies', 'depends_on_requirement_id', 'requirements', 'req_deps_target_org_fk', 'cascade'],
        ['tasks', 'project_id', 'projects', 'tasks_project_org_fk', 'cascade'],
        ['tasks', 'requirement_id', 'requirements', 'tasks_requirement_org_fk', 'no action'],
        ['tasks', 'parent_task_id', 'tasks', 'tasks_parent_org_fk', 'no action'],
        ['task_histories', 'task_id', 'tasks', 'task_histories_task_org_fk', 'cascade'],
        ['kanban_boards', 'project_id', 'projects', 'kanban_boards_project_org_fk', 'cascade'],
        ['kanban_columns', 'kanban_board_id', 'kanban_boards', 'kanban_columns_board_org_fk', 'cascade'],
        ['kanban_task_positions', 'task_id', 'tasks', 'kanban_positions_task_org_fk', 'cascade'],
        ['kanban_task_positions', 'kanban_column_id', 'kanban_columns', 'kanban_positions_column_org_fk', 'cascade'],
        ['project_documents', 'project_id', 'projects', 'project_documents_project_org_fk', 'cascade'],
        ['project_documents', 'document_template_id', 'document_templates', 'project_documents_template_org_fk', 'restrict'],
        ['project_comments', 'project_id', 'projects', 'project_comments_project_org_fk', 'cascade'],
        ['project_attachments', 'project_id', 'projects', 'project_attachments_project_org_fk', 'cascade'],
        ['project_activities', 'project_id', 'projects', 'project_activities_project_org_fk', 'cascade'],
    ];

    public function up(): void
    {
        $this->prepareCleanInstallation();
        $this->assertIntegrity();

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique('projects_code_unique');
        });

        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropUnique('document_templates_code_unique');
        });

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->unsignedBigInteger('organization_id')->nullable(false)->change();
                $table->foreign('organization_id', "{$tableName}_organization_fk")
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();
                $table->index('organization_id', "{$tableName}_org_idx");
            });
        }

        foreach ($this->anchorTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->unique(['id', 'organization_id'], "{$tableName}_id_org_unique");
            });
        }

        foreach ($this->relations as [$child, $foreignKey, $parent, $name, $onDelete]) {
            Schema::table($child, function (Blueprint $table) use ($foreignKey, $parent, $name, $onDelete): void {
                $table->foreign([$foreignKey, 'organization_id'], $name)
                    ->references(['id', 'organization_id'])
                    ->on($parent)
                    ->onDelete($onDelete);
            });
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->unique(['organization_id', 'code'], 'projects_org_code_unique');
        });

        Schema::table('document_templates', function (Blueprint $table): void {
            $table->unique(['organization_id', 'code'], 'document_templates_org_code_unique');
        });
    }

    public function down(): void
    {
        $this->assertGlobalCodesCanBeRestored();
    
        $isSqlite = DB::getDriverName() === 'sqlite';
    
        foreach (array_reverse($this->relations) as [$child, $foreignKey, , $name]) {
            Schema::table(
                $child,
                function (Blueprint $table) use (
                    $foreignKey,
                    $name,
                    $isSqlite
                ): void {
                    if ($isSqlite) {
                        $table->dropForeign([
                            $foreignKey,
                            'organization_id',
                        ]);
    
                        return;
                    }
    
                    $table->dropForeign($name);
                }
            );
        }
    
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique('projects_org_code_unique');
            $table->unique('code', 'projects_code_unique');
        });
    
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropUnique('document_templates_org_code_unique');
            $table->unique('code', 'document_templates_code_unique');
        });
    
        foreach (array_reverse($this->anchorTables) as $tableName) {
            Schema::table(
                $tableName,
                function (Blueprint $table) use ($tableName): void {
                    $table->dropUnique("{$tableName}_id_org_unique");
                }
            );
        }
    
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table(
                $tableName,
                function (Blueprint $table) use (
                    $tableName,
                    $isSqlite
                ): void {
                    if ($isSqlite) {
                        $table->dropForeign(['organization_id']);
                    } else {
                        $table->dropForeign(
                            "{$tableName}_organization_fk"
                        );
                    }
    
                    $table->dropIndex("{$tableName}_org_idx");
    
                    $table
                        ->unsignedBigInteger('organization_id')
                        ->nullable()
                        ->change();
                }
            );
        }
    }

    private function prepareCleanInstallation(): void
    {
        if (DB::table('organizations')->exists()) {
            return;
        }

        $tablesWithBusinessData = collect($this->tables)
            ->reject(fn (string $table): bool => $table === 'document_templates')
            ->filter(fn (string $table): bool => DB::table($table)->exists());

        if ($tablesWithBusinessData->isNotEmpty()) {
            throw new \LogicException(
                'A F4 encontrou dados de negócio sem organização: '.$tablesWithBusinessData->implode(', ').'.'
            );
        }

        $now = now();
        $organizationId = DB::table('organizations')->insertGetId([
            'name' => 'SGP Instalação Inicial',
            'slug' => CreateOrganization::BOOTSTRAP_SLUG,
            'type' => OrganizationType::Company->value,
            'status' => OrganizationStatus::Active->value,
            'timezone' => 'America/Belem',
            'settings' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('document_templates')
            ->whereNull('organization_id')
            ->update(['organization_id' => $organizationId]);
    }

    private function assertIntegrity(): void
    {
        $problems = [];

        foreach ($this->tables as $table) {
            $missing = DB::table($table)->whereNull('organization_id')->count();
            $invalid = DB::table($table)
                ->leftJoin('organizations', "{$table}.organization_id", '=', 'organizations.id')
                ->whereNotNull("{$table}.organization_id")
                ->whereNull('organizations.id')
                ->count();

            if ($missing > 0 || $invalid > 0) {
                $problems[] = "{$table} (sem organização: {$missing}; inválidos: {$invalid})";
            }
        }

        foreach ($this->relations as [$child, $foreignKey, $parent, $name]) {
            $parentAlias = "{$parent}_integrity_parent";
        
            $conflicts = DB::table($child)
                ->join(
                    "{$parent} as {$parentAlias}",
                    "{$child}.{$foreignKey}",
                    '=',
                    "{$parentAlias}.id"
                )
                ->whereNotNull("{$child}.{$foreignKey}")
                ->whereColumn(
                    "{$child}.organization_id",
                    '!=',
                    "{$parentAlias}.organization_id"
                )
                ->count();
        
            if ($conflicts > 0) {
                $problems[] = "{$name} ({$conflicts} conflito(s))";
            }
        }

        foreach (['projects', 'document_templates'] as $table) {
            $duplicates = DB::table($table)
                ->select(['organization_id', 'code'])
                ->whereNotNull('code')
                ->groupBy('organization_id', 'code')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicates > 0) {
                $problems[] = "{$table} ({$duplicates} código(s) duplicado(s) na mesma organização)";
            }
        }

        if ($problems !== []) {
            throw new \LogicException(
                "A F4 foi interrompida porque a integridade organizacional não está limpa:\n- ".implode("\n- ", $problems)
            );
        }
    }

    private function assertGlobalCodesCanBeRestored(): void
    {
        foreach (['projects', 'document_templates'] as $table) {
            $duplicates = DB::table($table)
                ->select('code')
                ->whereNotNull('code')
                ->groupBy('code')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($duplicates) {
                throw new \LogicException(
                    "O rollback da F4 exige reconciliar códigos repetidos entre organizações em {$table}."
                );
            }
        }
    }
};
