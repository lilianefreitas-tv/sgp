<?php

namespace App\Console\Commands;

use App\Enums\GlobalProfile;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Throwable;

class ImportSelectiveProjectData extends Command
{
    protected $signature = 'sgp:import-selective-project-data
                            {directory : Pasta que contém os seis CSVs e o manifesto SHA-256}
                            {--owner-email= : E-mail da nova Administradora principal}
                            {--mppa-slug=mppa : Slug da organização dos projetos 1, 2 e 3}
                            {--sgp-slug=sgp : Slug da organização do projeto 4}
                            {--apply : Confirma a gravação transacional; sem esta opção ocorre somente a simulação}
                            {--report= : Caminho opcional para salvar o relatório JSON}';

    protected $description = 'Valida e importa o núcleo seletivo de projetos da produção legada';

    /** @var array<string, int> */
    private const EXPECTED_COUNTS = [
        'projects' => 4,
        'requirements' => 41,
        'requirement_versions' => 9,
        'requirement_dependencies' => 0,
        'tasks' => 25,
        'task_histories' => 25,
    ];

    /** @var array<string, list<string>> */
    private const HEADERS = [
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

    /** @var array<int, string> */
    private const PROJECT_ORGANIZATIONS = [
        1 => 'mppa',
        2 => 'mppa',
        3 => 'mppa',
        4 => 'sgp',
    ];

    /** @var array<int, string> */
    private const PROJECT_CODES = [
        1 => 'PRJ-0001',
        2 => 'PRJ-0002',
        3 => 'PRJ-0003',
        4 => 'PRJ-0001',
    ];

    /** @var list<string> */
    private const BOOLEAN_COLUMNS = ['is_active'];

    public function handle(): int
    {
        try {
            $directory = $this->resolveDirectory((string) $this->argument('directory'));
            $owner = $this->resolveOwner();
            $organizations = $this->resolveOrganizations((string) $this->option('mppa-slug'), (string) $this->option('sgp-slug'));

            $this->assertTargetStructure();
            $this->assertOwnerMemberships((int) $owner->id, $organizations);
            $this->assertTargetIsEmpty();
            $this->verifyManifest($directory);

            $source = $this->readSource($directory);
            $this->assertSourceIntegrity($source);
            $prepared = $this->prepareRows($source, (int) $owner->id, $organizations);

            $applied = (bool) $this->option('apply');

            if ($applied) {
                DB::transaction(function () use ($prepared, $organizations): void {
                    $this->lockTargetTables();
                    $this->assertTargetIsEmpty();
                    $this->insertPreparedRows($prepared);
                    $this->updateProjectSequences($organizations);
                    $this->resetPostgreSqlSequences();
                    $this->assertImportedState($prepared, $organizations);
                }, 3);
            }

            $report = $this->buildReport($directory, (int) $owner->id, $organizations, $prepared, $applied);
            $this->writeReport($report);
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->components->info($applied
                ? 'IMPORTAÇÃO SELETIVA APLICADA E VALIDADA.'
                : 'SIMULAÇÃO APROVADA. Nenhum registro foi gravado.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveDirectory(string $input): string
    {
        $directory = realpath($input);

        if ($directory === false || ! is_dir($directory)) {
            throw new LogicException('A pasta informada não existe ou não pode ser lida.');
        }

        return $directory;
    }

    private function resolveOwner(): object
    {
        $email = mb_strtolower(trim((string) $this->option('owner-email')));

        if ($email === '') {
            throw new LogicException('Informe --owner-email com o e-mail da nova Administradora principal.');
        }

        $owner = DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($owner === null) {
            throw new LogicException('A conta indicada em --owner-email não existe no banco de destino.');
        }

        if (! (bool) $owner->is_active || $owner->global_profile !== GlobalProfile::Administrator->value) {
            throw new LogicException('A conta indicada deve ser uma Superadmin ativa.');
        }

        return $owner;
    }

    /** @return array<string, int> */
    private function resolveOrganizations(string $mppaSlug, string $sgpSlug): array
    {
        $slugs = [
            'mppa' => trim($mppaSlug),
            'sgp' => trim($sgpSlug),
        ];

        if ($slugs['mppa'] === '' || $slugs['sgp'] === '' || $slugs['mppa'] === $slugs['sgp']) {
            throw new LogicException('Os slugs das organizações devem ser distintos e não vazios.');
        }

        $organizations = [];

        foreach ($slugs as $key => $slug) {
            $id = DB::table('organizations')->where('slug', $slug)->value('id');

            if ($id === null) {
                throw new LogicException("A organização de destino [{$slug}] não existe.");
            }

            $organizations[$key] = (int) $id;
        }

        return $organizations;
    }

    private function assertTargetStructure(): void
    {
        $required = [
            'organizations' => ['id', 'slug', 'next_project_number'],
            'organization_memberships' => ['organization_id', 'user_id', 'role_code', 'status'],
            'projects' => array_merge(self::HEADERS['projects'], ['organization_id', 'execution_nature', 'financial_management_mode']),
            'requirements' => array_merge(self::HEADERS['requirements'], ['organization_id']),
            'requirement_versions' => array_merge(self::HEADERS['requirement_versions'], ['organization_id']),
            'requirement_dependencies' => array_merge(self::HEADERS['requirement_dependencies'], ['organization_id']),
            'tasks' => array_merge(self::HEADERS['tasks'], ['organization_id']),
            'task_histories' => array_merge(self::HEADERS['task_histories'], ['organization_id']),
        ];

        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new LogicException("O banco de destino não possui a tabela {$table}.");
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new LogicException("O banco de destino não possui {$table}.{$column}.");
                }
            }
        }
    }

    /** @param array<string, int> $organizations */
    private function assertOwnerMemberships(int $ownerId, array $organizations): void
    {
        foreach ($organizations as $key => $organizationId) {
            $valid = DB::table('organization_memberships')
                ->where('organization_id', $organizationId)
                ->where('user_id', $ownerId)
                ->where('role_code', OrganizationRole::Owner->value)
                ->where('status', OrganizationMembershipStatus::Active->value)
                ->exists();

            if (! $valid) {
                throw new LogicException("A Administradora principal não possui vínculo owner ativo na organização {$key}.");
            }
        }
    }

    private function assertTargetIsEmpty(): void
    {
        $occupied = collect(array_keys(self::EXPECTED_COUNTS))
            ->filter(fn (string $table): bool => DB::table($table)->exists())
            ->values();

        if ($occupied->isNotEmpty()) {
            throw new LogicException('O banco de destino já possui dados nas tabelas: '.$occupied->implode(', ').'.');
        }
    }

    private function verifyManifest(string $directory): void
    {
        $path = $directory.DIRECTORY_SEPARATOR.'manifest-sha256.csv';
        $rows = $this->readCsv($path, ['Tabela', 'Esperados', 'Exportados', 'Status', 'SHA256']);
        $manifest = [];

        foreach ($rows as $row) {
            $table = (string) $row['Tabela'];

            if (isset($manifest[$table])) {
                throw new LogicException("O manifesto repete a tabela {$table}.");
            }

            $manifest[$table] = $row;
        }

        foreach (self::EXPECTED_COUNTS as $table => $expected) {
            $entry = $manifest[$table] ?? null;

            if ($entry === null
                || (int) $entry['Esperados'] !== $expected
                || (int) $entry['Exportados'] !== $expected
                || $entry['Status'] !== 'OK') {
                throw new LogicException("O manifesto não aprova integralmente a tabela {$table}.");
            }

            $csv = $directory.DIRECTORY_SEPARATOR."{$table}.csv";
            $actualHash = strtoupper((string) hash_file('sha256', $csv));
            $expectedHash = strtoupper(trim((string) $entry['SHA256']));

            if ($actualHash === '' || ! hash_equals($expectedHash, $actualHash)) {
                throw new LogicException("O SHA-256 de {$table}.csv diverge do manifesto.");
            }
        }

        $unexpected = array_diff(array_keys($manifest), array_keys(self::EXPECTED_COUNTS));

        if ($unexpected !== []) {
            throw new LogicException('O manifesto contém tabelas fora do escopo: '.implode(', ', $unexpected).'.');
        }
    }

    /** @return array<string, list<array<string, string|null>>> */
    private function readSource(string $directory): array
    {
        $source = [];

        foreach (self::HEADERS as $table => $headers) {
            $source[$table] = $this->readCsv($directory.DIRECTORY_SEPARATOR."{$table}.csv", $headers);

            if (count($source[$table]) !== self::EXPECTED_COUNTS[$table]) {
                throw new LogicException(
                    "Contagem divergente em {$table}: esperado ".self::EXPECTED_COUNTS[$table].'; encontrado '.count($source[$table]).'.'
                );
            }
        }

        return $source;
    }

    /**
     * @param list<string> $expectedHeaders
     * @return list<array<string, string|null>>
     */
    private function readCsv(string $path, array $expectedHeaders): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new LogicException('Arquivo ausente ou ilegível: '.basename($path).'.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new LogicException('Não foi possível ler '.basename($path).'.');
        }

        $records = $this->parseCsv($contents, basename($path));

        if ($records === []) {
            throw new LogicException('O arquivo '.basename($path).' não possui cabeçalho.');
        }

        $header = array_shift($records)['values'];
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];

        if ($header !== $expectedHeaders) {
            throw new LogicException('Cabeçalho inesperado em '.basename($path).'.');
        }

        $rows = [];

        foreach ($records as $index => $record) {
            if (count($record['values']) !== count($expectedHeaders)) {
                throw new LogicException('Quantidade de colunas inválida em '.basename($path).' na linha '.($index + 2).'.');
            }

            $row = [];

            foreach ($expectedHeaders as $column => $name) {
                $value = $record['values'][$column];
                $row[$name] = $value === '' && ! $record['quoted'][$column] ? null : $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Analisa RFC 4180 preservando a diferença do COPY entre NULL vazio e string vazia entre aspas.
     *
     * @return list<array{values: list<string>, quoted: list<bool>}>
     */
    private function parseCsv(string $contents, string $name): array
    {
        $records = [];
        $values = [];
        $quotedFields = [];
        $field = '';
        $quoted = false;
        $inQuotes = false;
        $length = strlen($contents);

        for ($index = 0; $index < $length; $index++) {
            $character = $contents[$index];

            if ($inQuotes) {
                if ($character === '"') {
                    if ($index + 1 < $length && $contents[$index + 1] === '"') {
                        $field .= '"';
                        $index++;
                    } else {
                        $inQuotes = false;
                    }
                } else {
                    $field .= $character;
                }

                continue;
            }

            if ($character === '"' && $field === '') {
                $quoted = true;
                $inQuotes = true;
            } elseif ($character === ',') {
                $values[] = $field;
                $quotedFields[] = $quoted;
                $field = '';
                $quoted = false;
            } elseif ($character === "\n" || $character === "\r") {
                if ($character === "\r" && $index + 1 < $length && $contents[$index + 1] === "\n") {
                    $index++;
                }

                $values[] = $field;
                $quotedFields[] = $quoted;
                $records[] = ['values' => $values, 'quoted' => $quotedFields];
                $values = [];
                $quotedFields = [];
                $field = '';
                $quoted = false;
            } else {
                $field .= $character;
            }
        }

        if ($inQuotes) {
            throw new LogicException("Aspas não encerradas em {$name}.");
        }

        if ($field !== '' || $quoted || $values !== []) {
            $values[] = $field;
            $quotedFields[] = $quoted;
            $records[] = ['values' => $values, 'quoted' => $quotedFields];
        }

        return $records;
    }

    /** @param array<string, list<array<string, string|null>>> $source */
    private function assertSourceIntegrity(array $source): void
    {
        $projects = $this->indexById('projects', $source['projects']);

        if (array_keys($projects) !== [1, 2, 3, 4]) {
            throw new LogicException('Os projetos de origem devem possuir exatamente os IDs 1, 2, 3 e 4.');
        }

        foreach ([1 => 'PRJ-0001', 2 => 'PRJ-0002', 3 => 'PRJ-0003', 4 => 'PRJ-0004'] as $id => $code) {
            if ($projects[$id]['code'] !== $code) {
                throw new LogicException("Código inesperado no projeto {$id}.");
            }
        }

        $requirements = $this->indexById('requirements', $source['requirements']);
        $versions = $this->indexById('requirement_versions', $source['requirement_versions']);
        $dependencies = $this->indexById('requirement_dependencies', $source['requirement_dependencies']);
        $tasks = $this->indexById('tasks', $source['tasks']);
        $histories = $this->indexById('task_histories', $source['task_histories']);

        $this->assertUniquePairs('requirements', $source['requirements'], 'project_id', 'code');
        $this->assertUniquePairs('requirement_versions', $source['requirement_versions'], 'requirement_id', 'version_number');
        $this->assertUniquePairs('requirement_dependencies', $source['requirement_dependencies'], 'requirement_id', 'depends_on_requirement_id');
        $this->assertUniquePairs('tasks', $source['tasks'], 'project_id', 'code');

        foreach ($requirements as $row) {
            $this->requireReference('requirements.project_id', $row['project_id'], $projects);
        }

        foreach ($versions as $row) {
            $this->requireReference('requirement_versions.requirement_id', $row['requirement_id'], $requirements);
            $this->requirePositiveId('requirement_versions.changed_by', $row['changed_by']);
        }

        foreach ($dependencies as $row) {
            $requirement = $this->requireReference('requirement_dependencies.requirement_id', $row['requirement_id'], $requirements);
            $target = $this->requireReference('requirement_dependencies.depends_on_requirement_id', $row['depends_on_requirement_id'], $requirements);

            if ($requirement['id'] === $target['id'] || $requirement['project_id'] !== $target['project_id']) {
                throw new LogicException('Há dependência de requisito reflexiva ou entre projetos distintos.');
            }
        }

        foreach ($tasks as $row) {
            $project = $this->requireReference('tasks.project_id', $row['project_id'], $projects);

            if ($row['requirement_id'] !== null) {
                $requirement = $this->requireReference('tasks.requirement_id', $row['requirement_id'], $requirements);

                if ($project['id'] !== $requirement['project_id']) {
                    throw new LogicException("A tarefa {$row['id']} referencia requisito de outro projeto.");
                }
            }

            if ($row['parent_task_id'] !== null) {
                $parent = $this->requireReference('tasks.parent_task_id', $row['parent_task_id'], $tasks);

                if ($row['id'] === $parent['id'] || $project['id'] !== $parent['project_id']) {
                    throw new LogicException("A tarefa {$row['id']} possui hierarquia inválida.");
                }
            }
        }

        foreach ($histories as $row) {
            $this->requireReference('task_histories.task_id', $row['task_id'], $tasks);
            $this->requirePositiveId('task_histories.changed_by', $row['changed_by']);

            if ($row['changed_fields'] !== null) {
                json_decode($row['changed_fields'], true, 512, JSON_THROW_ON_ERROR);
            }
        }
    }

    /**
     * @param list<array<string, string|null>> $rows
     * @return array<int, array<string, string|null>>
     */
    private function indexById(string $table, array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $id = $this->requirePositiveId("{$table}.id", $row['id']);

            if (isset($indexed[$id])) {
                throw new LogicException("ID duplicado em {$table}: {$id}.");
            }

            $row['id'] = (string) $id;
            $indexed[$id] = $row;
        }

        ksort($indexed);

        return $indexed;
    }

    /** @param list<array<string, string|null>> $rows */
    private function assertUniquePairs(string $table, array $rows, string $first, string $second): void
    {
        $seen = [];

        foreach ($rows as $row) {
            $key = ($row[$first] ?? 'NULL').'|'.($row[$second] ?? 'NULL');

            if (isset($seen[$key])) {
                throw new LogicException("Par duplicado em {$table}: {$first}/{$second}.");
            }

            $seen[$key] = true;
        }
    }

    /**
     * @param array<int, array<string, string|null>> $rows
     * @return array<string, string|null>
     */
    private function requireReference(string $relation, ?string $value, array $rows): array
    {
        $id = $this->requirePositiveId($relation, $value);
        $row = $rows[$id] ?? null;

        if ($row === null) {
            throw new LogicException("Referência órfã em {$relation}: {$id}.");
        }

        return $row;
    }

    private function requirePositiveId(string $field, ?string $value): int
    {
        if ($value === null || ! preg_match('/^[1-9]\d*$/', $value)) {
            throw new LogicException("Identificador inválido em {$field}.");
        }

        return (int) $value;
    }

    /**
     * @param array<string, list<array<string, string|null>>> $source
     * @param array<string, int> $organizations
     * @return array<string, list<array<string, mixed>>>
     */
    private function prepareRows(array $source, int $ownerId, array $organizations): array
    {
        $projectOrganizations = [];
        $requirementOrganizations = [];
        $taskOrganizations = [];
        $prepared = array_fill_keys(array_keys(self::EXPECTED_COUNTS), []);

        foreach ($source['projects'] as $row) {
            $id = (int) $row['id'];
            $organization = $organizations[self::PROJECT_ORGANIZATIONS[$id]];
            $projectOrganizations[$id] = $organization;
            $row['code'] = self::PROJECT_CODES[$id];
            $row['client_id'] = null;
            $row['manager_id'] = $ownerId;
            $row['organization_id'] = $organization;
            $prepared['projects'][] = $this->normalizeRow($row);
        }

        foreach ($source['requirements'] as $row) {
            $organization = $projectOrganizations[(int) $row['project_id']];
            $requirementOrganizations[(int) $row['id']] = $organization;
            $row['responsible_id'] = $row['responsible_id'] === null ? null : $ownerId;
            $row['organization_id'] = $organization;
            $prepared['requirements'][] = $this->normalizeRow($row);
        }

        foreach ($source['requirement_versions'] as $row) {
            $row['changed_by'] = $ownerId;
            $row['organization_id'] = $requirementOrganizations[(int) $row['requirement_id']];
            $prepared['requirement_versions'][] = $this->normalizeRow($row);
        }

        foreach ($source['requirement_dependencies'] as $row) {
            $row['organization_id'] = $requirementOrganizations[(int) $row['requirement_id']];
            $prepared['requirement_dependencies'][] = $this->normalizeRow($row);
        }

        foreach ($source['tasks'] as $row) {
            $organization = $projectOrganizations[(int) $row['project_id']];
            $taskOrganizations[(int) $row['id']] = $organization;
            $row['responsible_id'] = $row['responsible_id'] === null ? null : $ownerId;
            $row['organization_id'] = $organization;
            $prepared['tasks'][] = $this->normalizeRow($row);
        }

        foreach ($source['task_histories'] as $row) {
            $row['changed_by'] = $ownerId;
            $row['organization_id'] = $taskOrganizations[(int) $row['task_id']];
            $prepared['task_histories'][] = $this->normalizeRow($row);
        }

        return $prepared;
    }

    /** @param array<string, string|int|null> $row @return array<string, mixed> */
    private function normalizeRow(array $row): array
    {
        foreach (self::BOOLEAN_COLUMNS as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = match (strtolower((string) $row[$column])) {
                    't', 'true', '1' => true,
                    'f', 'false', '0' => false,
                    default => throw new LogicException("Valor booleano inválido em {$column}."),
                };
            }
        }

        return $row;
    }

    private function lockTargetTables(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('LOCK TABLE projects, requirements, requirement_versions, requirement_dependencies, tasks, task_histories IN ACCESS EXCLUSIVE MODE');
    }

    /** @param array<string, list<array<string, mixed>>> $prepared */
    private function insertPreparedRows(array $prepared): void
    {
        DB::table('projects')->insert($prepared['projects']);
        DB::table('requirements')->insert($prepared['requirements']);
        DB::table('requirement_versions')->insert($prepared['requirement_versions']);

        if ($prepared['requirement_dependencies'] !== []) {
            DB::table('requirement_dependencies')->insert($prepared['requirement_dependencies']);
        }

        $parents = [];
        $tasks = [];

        foreach ($prepared['tasks'] as $row) {
            $parents[(int) $row['id']] = $row['parent_task_id'];
            $row['parent_task_id'] = null;
            $tasks[] = $row;
        }

        DB::table('tasks')->insert($tasks);

        foreach ($parents as $taskId => $parentId) {
            if ($parentId !== null) {
                DB::table('tasks')->where('id', $taskId)->update(['parent_task_id' => $parentId]);
            }
        }

        DB::table('task_histories')->insert($prepared['task_histories']);
    }

    /** @param array<string, int> $organizations */
    private function updateProjectSequences(array $organizations): void
    {
        DB::table('organizations')->where('id', $organizations['mppa'])->update(['next_project_number' => 4]);
        DB::table('organizations')->where('id', $organizations['sgp'])->update(['next_project_number' => 2]);
    }

    private function resetPostgreSqlSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys(self::EXPECTED_COUNTS) as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('public.{$table}', 'id'), GREATEST(COALESCE(MAX(id), 0), 1), MAX(id) IS NOT NULL) FROM public.{$table}"
            );
        }
    }

    /**
     * @param array<string, list<array<string, mixed>>> $prepared
     * @param array<string, int> $organizations
     */
    private function assertImportedState(array $prepared, array $organizations): void
    {
        foreach (self::EXPECTED_COUNTS as $table => $expected) {
            $actual = DB::table($table)->count();

            if ($actual !== $expected || $actual !== count($prepared[$table])) {
                throw new LogicException("Validação pós-importação divergente em {$table}.");
            }

            if ($actual > 0 && DB::table($table)->whereNull('organization_id')->exists()) {
                throw new LogicException("A importação deixou registros sem organização em {$table}.");
            }
        }

        foreach (self::PROJECT_CODES as $id => $code) {
            $organization = $organizations[self::PROJECT_ORGANIZATIONS[$id]];
            $valid = DB::table('projects')
                ->where('id', $id)
                ->where('organization_id', $organization)
                ->where('code', $code)
                ->whereNull('client_id')
                ->exists();

            if (! $valid) {
                throw new LogicException("Validação pós-importação divergente no projeto {$id}.");
            }
        }

        $relations = [
            ['requirements', 'project_id', 'projects'],
            ['requirement_versions', 'requirement_id', 'requirements'],
            ['requirement_dependencies', 'requirement_id', 'requirements'],
            ['requirement_dependencies', 'depends_on_requirement_id', 'requirements'],
            ['tasks', 'project_id', 'projects'],
            ['tasks', 'requirement_id', 'requirements'],
            ['tasks', 'parent_task_id', 'tasks'],
            ['task_histories', 'task_id', 'tasks'],
        ];

        foreach ($relations as $index => [$child, $foreignKey, $parent]) {
            $alias = "import_parent_{$index}";
            $conflicts = DB::table($child)
                ->join("{$parent} as {$alias}", "{$child}.{$foreignKey}", '=', "{$alias}.id")
                ->whereNotNull("{$child}.{$foreignKey}")
                ->whereColumn("{$child}.organization_id", '!=', "{$alias}.organization_id")
                ->count();

            if ($conflicts > 0) {
                throw new LogicException("A relação {$child}.{$foreignKey} ficou com conflito organizacional.");
            }
        }
    }

    /**
     * @param array<string, int> $organizations
     * @param array<string, list<array<string, mixed>>> $prepared
     * @return array<string, mixed>
     */
    private function buildReport(string $directory, int $ownerId, array $organizations, array $prepared, bool $applied): array
    {
        return [
            'status' => 'approved',
            'mode' => $applied ? 'apply' : 'dry-run',
            'source_directory' => $directory,
            'owner_user_id' => $ownerId,
            'organizations' => $organizations,
            'project_mapping' => [
                '1' => ['organization_id' => $organizations['mppa'], 'code' => 'PRJ-0001'],
                '2' => ['organization_id' => $organizations['mppa'], 'code' => 'PRJ-0002'],
                '3' => ['organization_id' => $organizations['mppa'], 'code' => 'PRJ-0003'],
                '4' => ['organization_id' => $organizations['sgp'], 'code' => 'PRJ-0001'],
            ],
            'counts' => collect($prepared)
                ->map(fn (array $rows): int => count($rows))
                ->all(),
            'remapping' => [
                'projects.client_id' => null,
                'projects.manager_id' => $ownerId,
                'non_null_responsible_id' => $ownerId,
                'changed_by' => $ownerId,
            ],
            'excluded' => ['clients', 'project_user', 'documents', 'files', 'kanban', 'collaboration'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $report */
    private function writeReport(array $report): void
    {
        $path = trim((string) $this->option('report'));

        if ($path === '') {
            return;
        }

        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new LogicException('A pasta do relatório não existe ou não permite gravação.');
        }

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new LogicException('Não foi possível salvar o relatório JSON.');
        }
    }
}
