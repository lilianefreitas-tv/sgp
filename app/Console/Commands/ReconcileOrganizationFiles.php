<?php

namespace App\Console\Commands;

use App\Services\OrganizationAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReconcileOrganizationFiles extends Command
{
    protected $signature = 'sgp:reconcile-organization-files
                            {--apply : Copia os arquivos e atualiza os caminhos e hashes}';

    protected $description = 'Reconcilia anexos e documentos legados com o armazenamento segregado por organização';

    /** @var array<int, array{processed: int, changed: int, missing: int}> */
    private array $organizations = [];

    public function handle(OrganizationAuditService $audit): int
    {
        $apply = (bool) $this->option('apply');
        $this->components->info($apply ? 'Modo: Aplicação' : 'Modo: Simulação');

        $totals = ['processed' => 0, 'changed' => 0, 'missing' => 0];

        DB::table('project_attachments')->orderBy('id')->chunkById(100, function ($rows) use (&$totals, $apply): void {
            foreach ($rows as $row) {
                $expected = "organizations/{$row->organization_id}/projects/{$row->project_id}/attachments/".basename($row->path);
                $this->reconcileOne(
                    'project_attachments',
                    (int) $row->id,
                    (int) $row->organization_id,
                    (string) $row->disk,
                    (string) $row->path,
                    $expected,
                    'path',
                    'sha256',
                    $apply,
                    $totals,
                );
            }
        });

        DB::table('project_documents')->orderBy('id')->chunkById(100, function ($rows) use (&$totals, $apply): void {
            foreach ($rows as $row) {
                $disk = filled($row->disk) ? (string) $row->disk : (string) config('sgp.storage.private_disk', 'local');
                $folder = "organizations/{$row->organization_id}/projects/{$row->project_id}/generated-documents/{$row->type}";

                $this->reconcileOne(
                    'project_documents',
                    (int) $row->id,
                    (int) $row->organization_id,
                    $disk,
                    (string) $row->docx_path,
                    $folder.'/'.basename($row->docx_path),
                    'docx_path',
                    'docx_sha256',
                    $apply,
                    $totals,
                    ['disk' => $disk],
                );

                $this->reconcileOne(
                    'project_documents',
                    (int) $row->id,
                    (int) $row->organization_id,
                    $disk,
                    (string) $row->pdf_path,
                    $folder.'/'.basename($row->pdf_path),
                    'pdf_path',
                    'pdf_sha256',
                    $apply,
                    $totals,
                    ['disk' => $disk],
                );
            }
        });

        $this->table(
            ['Arquivos verificados', 'A ajustar', 'Não encontrados'],
            [[$totals['processed'], $totals['changed'], $totals['missing']]],
        );

        if ($totals['missing'] > 0) {
            $this->components->error('A reconciliação encontrou arquivos ausentes. Corrija-os antes de concluir a F7.');

            return self::FAILURE;
        }

        if (! $apply) {
            $this->components->info('Simulação concluída. Nenhum arquivo ou registro foi alterado.');

            return self::SUCCESS;
        }

        foreach ($this->organizations as $organizationId => $summary) {
            $audit->record(
                'organization.files.reconciled',
                'success',
                'organization',
                $organizationId,
                $summary,
                $organizationId,
            );
        }

        $this->components->info('Reconciliação aplicada. Os arquivos de origem foram preservados por segurança.');

        return self::SUCCESS;
    }

    /** @param array<string, int> $totals @param array<string, mixed> $extraUpdates */
    private function reconcileOne(
        string $table,
        int $id,
        int $organizationId,
        string $disk,
        string $currentPath,
        string $expectedPath,
        string $pathColumn,
        string $hashColumn,
        bool $apply,
        array &$totals,
        array $extraUpdates = [],
    ): void {
        $totals['processed']++;
        $this->incrementOrganization($organizationId, 'processed');

        if (! Storage::disk($disk)->exists($currentPath)) {
            $totals['missing']++;
            $this->incrementOrganization($organizationId, 'missing');
            $this->components->warn("Ausente: {$disk}:{$currentPath}");

            return;
        }

        $hash = $this->hash($disk, $currentPath);
        $currentHash = DB::table($table)->where('id', $id)->value($hashColumn);
        $needsChange = $currentPath !== $expectedPath || $currentHash !== $hash;

        if (! $needsChange) {
            return;
        }

        $totals['changed']++;
        $this->incrementOrganization($organizationId, 'changed');

        if (! $apply) {
            $this->line("Ajustar: {$disk}:{$currentPath} -> {$expectedPath}");

            return;
        }

        if ($currentPath !== $expectedPath) {
            $stream = Storage::disk($disk)->readStream($currentPath);

            if ($stream === false) {
                throw new RuntimeException("Não foi possível ler {$disk}:{$currentPath}.");
            }

            try {
                if (! Storage::disk($disk)->put($expectedPath, $stream)) {
                    throw new RuntimeException("Não foi possível copiar {$disk}:{$expectedPath}.");
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! Storage::disk($disk)->exists($expectedPath)
                || $this->hash($disk, $expectedPath) !== $hash) {
                Storage::disk($disk)->delete($expectedPath);
                throw new RuntimeException("A cópia de {$expectedPath} não passou na verificação SHA-256.");
            }
        }

        DB::table($table)->where('id', $id)->update(array_merge($extraUpdates, [
            $pathColumn => $expectedPath,
            $hashColumn => $hash,
        ]));
    }

    private function hash(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if ($stream === false) {
            throw new RuntimeException("Não foi possível identificar {$disk}:{$path}.");
        }

        $context = hash_init('sha256');

        try {
            hash_update_stream($context, $stream);
        } finally {
            fclose($stream);
        }

        return hash_final($context);
    }

    private function incrementOrganization(int $organizationId, string $field): void
    {
        $this->organizations[$organizationId] ??= ['processed' => 0, 'changed' => 0, 'missing' => 0];
        $this->organizations[$organizationId][$field]++;
    }
}
