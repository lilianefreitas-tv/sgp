<?php

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, int> */
    private const LEGACY_COUNTS = [
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
        'document_templates' => 4,
        'project_documents' => 6,
        'project_comments' => 0,
        'project_attachments' => 0,
        'project_activities' => 50,
    ];

    /** @var list<string> */
    private const BUSINESS_TABLES = [
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

    /** @var array<int, array{code: string, name: string, client_id: int, organization: string}> */
    private const PROJECTS = [
        1 => [
            'code' => 'PRJ-0001',
            'name' => 'Projeto Atitude – Equidade de Gênero',
            'client_id' => 1,
            'organization' => 'mppa',
        ],
        2 => [
            'code' => 'PRJ-0002',
            'name' => 'RotaMP – Sistema de Agendamento e Gestão de Motoristas',
            'client_id' => 2,
            'organization' => 'mppa',
        ],
        3 => [
            'code' => 'PRJ-0003',
            'name' => 'GeoMP – Plataforma de Inteligência Geoespacial',
            'client_id' => 2,
            'organization' => 'mppa',
        ],
        4 => [
            'code' => 'PRJ-0004',
            'name' => 'SGP - Sistema de Gestão de Projetos de Software',
            'client_id' => 3,
            'organization' => 'sgp',
        ],
    ];

    /** @var array<int, string> */
    private const CLIENTS = [
        1 => 'Projeto Colabs/MPPA',
        2 => 'Promotoria de Justiça de Altamira',
        3 => 'Liliane de Freitas Terra Vieira',
    ];

    /** @var array<string, string> */
    private const TEMPORARY_TEMPLATE_CODES = [
        'MOD-001' => 'TMP-SGP-MOD-001',
        'MOD-002' => 'TMP-SGP-MOD-002',
        'MOD-003' => 'TMP-SGP-MOD-003',
        'MOD-004' => 'TMP-SGP-MOD-004',
    ];

    /** @var array<string, array{parent: string, foreign_key: string}> */
    private const PARENT_RELATIONS = [
        'project_user' => ['parent' => 'projects', 'foreign_key' => 'project_id'],
        'requirements' => ['parent' => 'projects', 'foreign_key' => 'project_id'],
        'requirement_versions' => ['parent' => 'requirements', 'foreign_key' => 'requirement_id'],
        'requirement_dependencies' => ['parent' => 'requirements', 'foreign_key' => 'requirement_id'],
        'tasks' => ['parent' => 'projects', 'foreign_key' => 'project_id'],
        'task_histories' => ['parent' => 'tasks', 'foreign_key' => 'task_id'],
        'kanban_boards' => ['parent' => 'projects', 'foreign_key' => 'project_id'],
        'kanban_columns' => ['parent' => 'kanban_boards', 'foreign_key' => 'kanban_board_id'],
        'kanban_task_positions' => ['parent' => 'tasks', 'foreign_key' => 'task_id'],
        'project_documents' => ['parent' => 'projects', 'foreign_key' => 'project_id'],
        'project_comments' => ['parent' => 'projects', 'foreign_key' => 'project_id'],
        'project_attachments' => ['parent' => 'projects', 'foreign_key' => 'project_id'],
        'project_activities' => ['parent' => 'projects', 'foreign_key' => 'project_id'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->lockTransitionTables();

            if ($this->transitionWasApplied()) {
                $this->assertTransitionedState();

                return;
            }

            if (! $this->hasLegacyBusinessData()) {
                $this->assertCleanInstallationState();

                return;
            }

            $this->assertLegacyInventory();

            $organizationIds = $this->createOrganizationsAndMemberships();
            $this->assignRoots($organizationIds);
            $this->assignChildren();
            $this->duplicateTemplatesForSgp($organizationIds['mppa'], $organizationIds['sgp']);

            $this->assertTransitionedState();
        }, 3);
    }

    public function down(): void
    {
        throw new \LogicException(
            'A transição seletiva da produção não admite migrate:rollback. '
            .'Restaure o PostgreSQL pelo ponto de recuperação aprovado antes do deploy.'
        );
    }

    private function lockTransitionTables(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = array_merge(
            ['users', 'organizations', 'organization_memberships'],
            self::BUSINESS_TABLES,
        );
        $quoted = collect($tables)
            ->unique()
            ->map(fn (string $table): string => '"'.str_replace('"', '""', $table).'"')
            ->implode(', ');

        DB::statement("LOCK TABLE {$quoted} IN ACCESS EXCLUSIVE MODE");
    }

    private function transitionWasApplied(): bool
    {
        return DB::table('organizations')->whereIn('slug', ['mppa', 'sgp'])->count() === 2;
    }

    private function hasLegacyBusinessData(): bool
    {
        return collect(self::BUSINESS_TABLES)
            ->reject(fn (string $table): bool => $table === 'document_templates')
            ->contains(fn (string $table): bool => DB::table($table)->exists());
    }

    private function assertCleanInstallationState(): void
    {
        if (DB::table('organizations')->exists() || DB::table('organization_memberships')->exists()) {
            $this->fail('instalação sem dados de projeto já possui organização ou vínculo parcial');
        }

        foreach (self::BUSINESS_TABLES as $table) {
            if ($table === 'document_templates') {
                continue;
            }

            if (DB::table($table)->exists()) {
                $this->fail("instalação limpa contém registros inesperados em {$table}");
            }
        }

        $assignedTemplates = DB::table('document_templates')->whereNotNull('organization_id')->count();

        if ($assignedTemplates > 0) {
            $this->fail('instalação limpa contém modelos já associados a uma organização');
        }
    }

    private function assertLegacyInventory(): void
    {
        if (DB::table('organizations')->exists() || DB::table('organization_memberships')->exists()) {
            $this->fail('o banco legado já possui organizações ou vínculos parciais');
        }

        if (DB::table('users')->count() !== 1) {
            $this->fail('o inventário aprovado exige exatamente uma conta de usuário');
        }

        $this->assertRow('users', 1, [
            'name' => 'Liliane de Freitas Terra Vieira',
            'global_profile' => 'administrator',
            'is_active' => true,
        ]);

        foreach (self::LEGACY_COUNTS as $table => $expected) {
            $actual = DB::table($table)->count();

            if ($actual !== $expected) {
                $this->fail("contagem divergente em {$table}: esperado {$expected}; encontrado {$actual}");
            }
        }

        foreach (self::BUSINESS_TABLES as $table) {
            $assigned = DB::table($table)->whereNotNull('organization_id')->count();

            if ($assigned > 0) {
                $this->fail("{$table} já possui {$assigned} registro(s) com organization_id");
            }
        }

        foreach (self::CLIENTS as $id => $name) {
            $this->assertRow('clients', $id, ['name' => $name]);
        }

        foreach (self::PROJECTS as $id => $project) {
            $this->assertRow('projects', $id, [
                'code' => $project['code'],
                'name' => $project['name'],
                'client_id' => $project['client_id'],
                'manager_id' => 1,
            ]);
        }

        $templateCodes = DB::table('document_templates')->orderBy('code')->pluck('code')->all();

        if ($templateCodes !== ['MOD-001', 'MOD-002', 'MOD-003', 'MOD-004']) {
            $this->fail('os quatro modelos documentais legados não correspondem a MOD-001 até MOD-004');
        }

        $this->assertLegacyRelations();
    }

    /** @return array{mppa: int, sgp: int} */
    private function createOrganizationsAndMemberships(): array
    {
        $now = now();
        $mppaId = DB::table('organizations')->insertGetId([
            'name' => 'Ministério Público do Estado do Pará',
            'slug' => 'mppa',
            'type' => OrganizationType::PublicBody->value,
            'status' => OrganizationStatus::Active->value,
            'timezone' => 'America/Belem',
            'settings' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sgpId = DB::table('organizations')->insertGetId([
            'name' => 'SGP',
            'slug' => 'sgp',
            'type' => OrganizationType::Team->value,
            'status' => OrganizationStatus::Active->value,
            'timezone' => 'America/Belem',
            'settings' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('organization_memberships')->insert([
            [
                'organization_id' => $mppaId,
                'user_id' => 1,
                'role_code' => OrganizationRole::Owner->value,
                'status' => OrganizationMembershipStatus::Active->value,
                'is_default' => true,
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'organization_id' => $sgpId,
                'user_id' => 1,
                'role_code' => OrganizationRole::Owner->value,
                'status' => OrganizationMembershipStatus::Active->value,
                'is_default' => false,
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return ['mppa' => (int) $mppaId, 'sgp' => (int) $sgpId];
    }

    /** @param array{mppa: int, sgp: int} $organizationIds */
    private function assignRoots(array $organizationIds): void
    {
        DB::table('clients')->whereIn('id', [1, 2])->update([
            'organization_id' => $organizationIds['mppa'],
        ]);
        DB::table('clients')->where('id', 3)->update([
            'organization_id' => $organizationIds['sgp'],
        ]);
        DB::table('projects')->whereIn('id', [1, 2, 3])->update([
            'organization_id' => $organizationIds['mppa'],
        ]);
        DB::table('projects')->where('id', 4)->update([
            'organization_id' => $organizationIds['sgp'],
        ]);
    }

    private function assignChildren(): void
    {
        foreach (self::PARENT_RELATIONS as $table => $relation) {
            $rows = DB::table($table)
                ->whereNull('organization_id')
                ->orderBy('id')
                ->get(['id', $relation['foreign_key']]);
            $organizationByParent = DB::table($relation['parent'])
                ->whereIn('id', $rows->pluck($relation['foreign_key'])->filter()->unique())
                ->pluck('organization_id', 'id');

            foreach ($rows as $row) {
                $parentId = $row->{$relation['foreign_key']};
                $organizationId = $organizationByParent[$parentId] ?? null;

                if ($organizationId === null) {
                    $this->fail("não foi possível determinar a organização de {$table}#{$row->id}");
                }

                DB::table($table)->where('id', $row->id)->update([
                    'organization_id' => (int) $organizationId,
                ]);
            }
        }
    }

    private function duplicateTemplatesForSgp(int $mppaId, int $sgpId): void
    {
        $templates = DB::table('document_templates')->orderBy('id')->get();
        $templateMap = [];

        DB::table('document_templates')->whereIn('id', $templates->pluck('id'))->update([
            'organization_id' => $mppaId,
        ]);

        foreach ($templates as $template) {
            $copy = (array) $template;
            $legacyId = (int) $copy['id'];
            unset($copy['id']);
            $copy['organization_id'] = $sgpId;
            $copy['code'] = self::TEMPORARY_TEMPLATE_CODES[(string) $template->code] ?? null;

            if ($copy['code'] === null) {
                $this->fail("o modelo {$legacyId} possui código não reconhecido");
            }

            $templateMap[$legacyId] = (int) DB::table('document_templates')->insertGetId($copy);
        }

        $documents = DB::table('project_documents')
            ->where('organization_id', $sgpId)
            ->orderBy('id')
            ->get(['id', 'document_template_id']);

        foreach ($documents as $document) {
            $newTemplateId = $templateMap[(int) $document->document_template_id] ?? null;

            if ($newTemplateId === null) {
                $this->fail("o documento {$document->id} referencia um modelo legado desconhecido");
            }

            DB::table('project_documents')->where('id', $document->id)->update([
                'document_template_id' => $newTemplateId,
            ]);
        }
    }

    private function assertTransitionedState(): void
    {
        if (DB::table('organizations')->count() !== 2) {
            $this->fail('o estado migrado deve possuir exatamente duas organizações');
        }

        $mppaId = $this->organizationId('mppa');
        $sgpId = $this->organizationId('sgp');

        $this->assertOrganization($mppaId, 'Ministério Público do Estado do Pará', 'public_body');
        $this->assertOrganization($sgpId, 'SGP', 'team');

        if (DB::table('organization_memberships')->count() !== 2) {
            $this->fail('o estado migrado deve possuir exatamente dois vínculos organizacionais');
        }

        foreach ([['id' => $mppaId, 'default' => true], ['id' => $sgpId, 'default' => false]] as $membership) {
            $row = DB::table('organization_memberships')
                ->where('organization_id', $membership['id'])
                ->where('user_id', 1)
                ->first();

            if ($row === null
                || $row->role_code !== OrganizationRole::Owner->value
                || $row->status !== OrganizationMembershipStatus::Active->value
                || (bool) $row->is_default !== $membership['default']) {
                $this->fail("vínculo organizacional inválido para a organização {$membership['id']}");
            }
        }

        foreach (self::LEGACY_COUNTS as $table => $expected) {
            $expectedAfter = $table === 'document_templates' ? 8 : $expected;
            $actual = DB::table($table)->count();

            if ($actual !== $expectedAfter) {
                $this->fail("contagem pós-migração divergente em {$table}: esperado {$expectedAfter}; encontrado {$actual}");
            }
        }

        foreach (self::BUSINESS_TABLES as $table) {
            $missing = DB::table($table)->whereNull('organization_id')->count();
            $invalid = DB::table($table)
                ->leftJoin('organizations', "{$table}.organization_id", '=', 'organizations.id')
                ->whereNotNull("{$table}.organization_id")
                ->whereNull('organizations.id')
                ->count();

            if ($missing > 0 || $invalid > 0) {
                $this->fail("{$table} ficou com {$missing} organização(ões) ausente(s) e {$invalid} inválida(s)");
            }
        }

        foreach ([1, 2] as $clientId) {
            $this->assertOrganizationValue('clients', $clientId, $mppaId);
        }
        $this->assertOrganizationValue('clients', 3, $sgpId);

        foreach ([1, 2, 3] as $projectId) {
            $this->assertOrganizationValue('projects', $projectId, $mppaId);
        }
        $this->assertOrganizationValue('projects', 4, $sgpId);

        $sgpCode = DB::table('projects')->where('id', 4)->value('code');

        if (! in_array($sgpCode, ['PRJ-0004', 'PRJ-0001'], true)) {
            $this->fail("o projeto SGP possui código inesperado: {$sgpCode}");
        }

        $mppaCodes = DB::table('document_templates')
            ->where('organization_id', $mppaId)
            ->orderBy('code')
            ->pluck('code')
            ->all();
        $sgpCodes = DB::table('document_templates')
            ->where('organization_id', $sgpId)
            ->orderBy('code')
            ->pluck('code')
            ->all();
        $finalCodes = ['MOD-001', 'MOD-002', 'MOD-003', 'MOD-004'];
        $temporaryCodes = array_values(self::TEMPORARY_TEMPLATE_CODES);

        if ($mppaCodes !== $finalCodes) {
            $this->fail("modelos documentais incompletos na organização {$mppaId}");
        }

        if ($sgpCodes !== $temporaryCodes && $sgpCodes !== $finalCodes) {
            $this->fail("modelos documentais incompletos na organização {$sgpId}");
        }

        $this->assertOrganizationRelations();
    }

    private function assertLegacyRelations(): void
    {
        $relations = [
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

        foreach ($relations as $index => [$child, $foreignKey, $parent]) {
            $alias = "legacy_parent_{$index}";
            $orphans = DB::table($child)
                ->leftJoin("{$parent} as {$alias}", "{$child}.{$foreignKey}", '=', "{$alias}.id")
                ->whereNotNull("{$child}.{$foreignKey}")
                ->whereNull("{$alias}.id")
                ->count();

            if ($orphans > 0) {
                $this->fail("a relação {$child}.{$foreignKey} possui {$orphans} órfão(s)");
            }
        }

        $this->assertSameProject('tasks', 'requirement_id', 'requirements', 'project_id');
        $this->assertSameProject('tasks', 'parent_task_id', 'tasks', 'project_id');
        $this->assertRequirementDependenciesStayInProject();
    }

    private function assertOrganizationRelations(): void
    {
        $relations = [
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

        foreach ($relations as $index => [$child, $foreignKey, $parent]) {
            $alias = "organization_parent_{$index}";
            $conflicts = DB::table($child)
                ->join("{$parent} as {$alias}", "{$child}.{$foreignKey}", '=', "{$alias}.id")
                ->whereNotNull("{$child}.{$foreignKey}")
                ->whereColumn("{$child}.organization_id", '!=', "{$alias}.organization_id")
                ->count();

            if ($conflicts > 0) {
                $this->fail("a relação {$child}.{$foreignKey} possui {$conflicts} conflito(s) organizacional(is)");
            }
        }
    }

    private function assertSameProject(
        string $child,
        string $foreignKey,
        string $parent,
        string $parentProjectKey,
        string $childProjectKey = 'project_id',
    ): void {
        $alias = 'same_project_parent';
        $conflicts = DB::table($child)
            ->join("{$parent} as {$alias}", "{$child}.{$foreignKey}", '=', "{$alias}.id")
            ->whereNotNull("{$child}.{$foreignKey}")
            ->whereColumn("{$child}.{$childProjectKey}", '!=', "{$alias}.{$parentProjectKey}")
            ->count();

        if ($conflicts > 0) {
            $this->fail("a relação {$child}.{$foreignKey} cruza {$conflicts} projeto(s)");
        }
    }

    private function assertRequirementDependenciesStayInProject(): void
    {
        $conflicts = DB::table('requirement_dependencies')
            ->join(
                'requirements as source_requirement',
                'requirement_dependencies.requirement_id',
                '=',
                'source_requirement.id',
            )
            ->join(
                'requirements as target_requirement',
                'requirement_dependencies.depends_on_requirement_id',
                '=',
                'target_requirement.id',
            )
            ->whereColumn('source_requirement.project_id', '!=', 'target_requirement.project_id')
            ->count();

        if ($conflicts > 0) {
            $this->fail("as dependências de requisitos cruzam {$conflicts} projeto(s)");
        }
    }

    /** @param array<string, mixed> $expected */
    private function assertRow(string $table, int $id, array $expected): void
    {
        $row = DB::table($table)->where('id', $id)->first();

        if ($row === null) {
            $this->fail("registro ausente: {$table}#{$id}");
        }

        foreach ($expected as $field => $value) {
            $actual = $row->{$field};
            $matches = is_bool($value)
                ? (bool) $actual === $value
                : (string) $actual === (string) $value;

            if (! $matches) {
                $this->fail("{$table}#{$id}.{$field} divergente");
            }
        }
    }

    private function assertOrganization(int $id, string $name, string $type): void
    {
        $this->assertRow('organizations', $id, [
            'name' => $name,
            'type' => $type,
            'status' => OrganizationStatus::Active->value,
            'timezone' => 'America/Belem',
        ]);
    }

    private function assertOrganizationValue(string $table, int $id, int $organizationId): void
    {
        $actual = DB::table($table)->where('id', $id)->value('organization_id');

        if ((int) $actual !== $organizationId) {
            $this->fail("{$table}#{$id} não pertence à organização esperada");
        }
    }

    private function organizationId(string $slug): int
    {
        $id = DB::table('organizations')->where('slug', $slug)->value('id');

        if ($id === null) {
            $this->fail("organização {$slug} ausente");
        }

        return (int) $id;
    }

    private function fail(string $message): never
    {
        throw new \LogicException("Transição BL-SGP-002 interrompida: {$message}.");
    }
};
