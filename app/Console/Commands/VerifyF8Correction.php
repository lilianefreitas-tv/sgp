<?php

namespace App\Console\Commands;

use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class VerifyF8Correction extends Command
{
    protected $signature = 'sgp:verify-f8-correction';

    protected $description = 'Verifica sequências, modelos e fusos após a correção da F8';

    public function handle(): int
    {
        $rows = [];
        $failed = false;

        foreach (Organization::query()->orderBy('id')->get() as $organization) {
            $projects = Project::withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->get(['id', 'code']);

            $projectNumbers = $projects->pluck('code')
                ->map(function (string $code): ?int {
                    return preg_match('/^PRJ-(\d{4,})$/', $code, $matches) === 1
                        ? (int) $matches[1]
                        : null;
                });
            $highestProjectNumber = $projectNumbers->filter(fn (?int $number): bool => $number !== null)->max() ?? 0;
            $sequenceOk = ! $projectNumbers->contains(null)
                && $projectNumbers->filter()->unique()->count() === $projectNumbers->filter()->count()
                && $organization->next_project_number > $highestProjectNumber;

            $activeTypes = DocumentTemplate::withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->get(['type'])
                ->map(fn (DocumentTemplate $template): string => $template->type instanceof DocumentType
                    ? $template->type->value
                    : (string) $template->type)
                ->unique()
                ->values();
            $templatesOk = collect(DocumentType::cases())
                ->every(fn (DocumentType $type): bool => $activeTypes->contains($type->value));

            $timezoneOk = in_array($organization->timezone, timezone_identifiers_list(), true);
            $localTime = $timezoneOk
                ? Carbon::now('UTC')->timezone($organization->timezone)->format('d/m/Y H:i')
                : 'fuso inválido';

            $status = $sequenceOk && $templatesOk && $timezoneOk ? 'OK' : 'FALHA';
            $failed = $failed || $status === 'FALHA';
            $rows[] = [
                $organization->name,
                $projects->pluck('code')->implode(', ') ?: '(nenhum)',
                $organization->next_project_number,
                $activeTypes->count().'/4',
                $organization->timezone,
                $localTime,
                $status,
            ];
        }

        $this->table(
            ['Organização', 'Projetos', 'Próximo', 'Modelos', 'Fuso', 'Hora local', 'Resultado'],
            $rows,
        );

        if ($failed) {
            $this->components->error('RESULTADO: FALHA');

            return self::FAILURE;
        }

        $this->components->info('RESULTADO: SUCESSO');

        return self::SUCCESS;
    }
}
