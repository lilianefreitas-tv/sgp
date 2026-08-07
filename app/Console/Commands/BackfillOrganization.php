<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\OrganizationBackfillService;
use Illuminate\Console\Command;

class BackfillOrganization extends Command
{
    protected $signature = 'sgp:backfill-organization
                            {organization : ID ou slug da organização inicial}
                            {--dry-run : Simula o preenchimento e desfaz a transação}
                            {--force : Executa sem pedir confirmação}';

    protected $description = 'Associa dados legados à organização inicial e reconcilia os vínculos';

    public function handle(OrganizationBackfillService $service): int
    {
        $identifier = trim((string) $this->argument('organization'));
        $organization = Organization::query()
            ->where('slug', $identifier)
            ->when(ctype_digit($identifier), fn ($query) => $query->orWhereKey((int) $identifier))
            ->first();

        if (! $organization) {
            $this->components->error("Organização [{$identifier}] não encontrada.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force') && ! $this->confirm(
            "Associar todos os dados legados ainda sem organização a [{$organization->name}]?",
        )) {
            $this->components->warn('Operação cancelada sem alterações.');

            return self::SUCCESS;
        }

        $report = $service->run($organization, $dryRun);

        $this->table(
            ['Tabela', 'Total', 'Preenchidos', 'Sem organização', 'Organização inválida'],
            collect($report['rows'])->map(
                fn (array $row, string $table): array => [
                    $table,
                    $row['total'],
                    $row['updated'],
                    $row['missing'],
                    $row['invalid'],
                ]
            )->values()->all(),
        );

        $conflictCount = array_sum($report['conflicts']);
        $this->line('Conflitos entre relações: '.$conflictCount);

        if (! $report['clean']) {
            $this->components->error('A reconciliação encontrou pendências. A transação foi desfeita.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->components->info('Simulação concluída sem pendências. Nenhuma alteração foi gravada.');
        } else {
            $this->components->info("Backfill concluído para [{$organization->name}].");
        }

        return self::SUCCESS;
    }
}
