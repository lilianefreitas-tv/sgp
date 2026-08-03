<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class DocumentGenerationService
{
    private const PRIMARY = '123B4A';

    private const ACCENT = '287EA1';

    private const TEXT = '24313A';

    private const MUTED = '667680';

    private const BORDER = 'DCE3E7';

    private const LIGHT = 'F3F7F8';

    public function generate(
        Project $project,
        DocumentTemplate $template,
        User $user,
        int $version,
    ): ProjectDocument {
        $project->load([
            'client',
            'manager',
            'memberships' => fn ($query) => $query->where('is_active', true)->with('user'),
            'requirements' => fn ($query) => $query->with('responsible')->orderBy('code'),
            'tasks' => fn ($query) => $query->with(['responsible', 'requirement', 'parent'])->orderBy('code'),
        ]);

        $generatedAt = now();
        $payload = $this->payload($project, $template, $user, $version, $generatedAt);
        $folder = 'generated-documents/'.$project->id.'/'.$template->type->value;
        $baseName = Str::slug($project->code.'-'.$template->type->slug().'-v'.$version);
        $storageBaseName = $baseName.'-'.Str::lower(Str::random(8));
        $docxFileName = $baseName.'.docx';
        $pdfFileName = $baseName.'.pdf';
        $docxPath = $folder.'/'.$storageBaseName.'.docx';
        $pdfPath = $folder.'/'.$storageBaseName.'.pdf';

        $disk = (string) config('sgp.storage.private_disk', 'local');
        $temporaryDocxPath = null;
        $temporaryPdfPath = null;

        try {
            $temporaryDocxPath = $this->temporaryPath('sgp-docx-');
            $temporaryPdfPath = $this->temporaryPath('sgp-pdf-');
            $this->writeDocx($payload, $temporaryDocxPath);
            $this->writePdf($payload, $temporaryPdfPath);
            $this->upload($disk, $docxPath, $temporaryDocxPath);
            $this->upload($disk, $pdfPath, $temporaryPdfPath);

            return ProjectDocument::create([
                'project_id' => $project->id,
                'document_template_id' => $template->id,
                'generated_by' => $user->id,
                'type' => $template->type,
                'title' => $template->type->label(),
                'version' => $version,
                'docx_path' => $docxPath,
                'pdf_path' => $pdfPath,
                'docx_file_name' => $docxFileName,
                'pdf_file_name' => $pdfFileName,
                'metadata' => [
                    'template_code' => $template->code,
                    'template_name' => $template->name,
                    'template_version' => $template->version,
                    'project_code' => $project->code,
                    'requirements_count' => $project->requirements->count(),
                    'tasks_count' => $project->tasks->count(),
                ],
                'generated_at' => $generatedAt,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete([$docxPath, $pdfPath]);
            throw $exception;
        } finally {
            foreach ([$temporaryDocxPath, $temporaryPdfPath] as $temporaryPath) {
                if (is_string($temporaryPath) && is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
            }
        }
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw new \RuntimeException('Não foi possível criar o arquivo temporário.');
        }

        return $path;
    }

    private function upload(string $disk, string $targetPath, string $temporaryPath): void
    {
        $stream = fopen($temporaryPath, 'rb');

        if ($stream === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo temporário.');
        }

        try {
            if (! Storage::disk($disk)->put($targetPath, $stream)) {
                throw new \RuntimeException('Não foi possível armazenar o documento gerado.');
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return array<string, mixed> */
    private function payload(
        Project $project,
        DocumentTemplate $template,
        User $user,
        int $version,
        Carbon $generatedAt,
    ): array {
        return [
            'project' => $project,
            'template' => $template,
            'generatedBy' => $user,
            'type' => $template->type,
            'title' => $template->type->label(),
            'version' => $version,
            'generatedAt' => $generatedAt,
            'members' => $project->memberships
                ->groupBy('user_id')
                ->map(fn (Collection $memberships) => [
                    'user' => $memberships->first()->user,
                    'roles' => $memberships->pluck('role')->map->label()->implode(', '),
                ])
                ->values(),
            'requirements' => $project->requirements,
            'tasks' => $project->tasks,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function writeDocx(array $payload, string $path): void
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);
        $phpWord->addTitleStyle(1, ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => self::PRIMARY], ['spaceBefore' => 240, 'spaceAfter' => 120]);
        $phpWord->addTitleStyle(2, ['name' => 'Arial', 'size' => 12, 'bold' => true, 'color' => self::ACCENT], ['spaceBefore' => 180, 'spaceAfter' => 80]);
        $phpWord->addTableStyle('SgpTable', [
            'borderSize' => 6,
            'borderColor' => self::BORDER,
            'cellMargin' => 100,
        ], [
            'bgColor' => self::PRIMARY,
            'color' => 'FFFFFF',
            'bold' => true,
        ]);

        $section = $phpWord->addSection([
            'paperSize' => 'A4',
            'marginTop' => 1134,
            'marginRight' => 1134,
            'marginBottom' => 1134,
            'marginLeft' => 1134,
        ]);

        $this->addHeaderAndFooter($section, $payload);
        $this->addCover($section, $payload);

        match ($payload['type']) {
            DocumentType::Vision => $this->addVisionContent($section, $payload),
            DocumentType::RequirementsList => $this->addRequirementsContent($section, $payload),
            DocumentType::TasksList => $this->addTasksContent($section, $payload),
            DocumentType::ConsolidatedBacklog => $this->addBacklogContent($section, $payload),
        };

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);
    }

    /** @param array<string, mixed> $payload */
    private function addHeaderAndFooter(Section $section, array $payload): void
    {
        $header = $section->addHeader();
        $headerTable = $header->addTable(['width' => 9500, 'unit' => 'dxa']);
        $headerTable->addRow();
        $brandCell = $headerTable->addCell(1200);
        $logoPath = public_path('images/sgp-logo.png');
        if (is_file($logoPath)) {
            $brandCell->addImage($logoPath, ['width' => 28, 'height' => 28]);
        } else {
            $brandCell->addText('SGP', ['bold' => true, 'color' => self::PRIMARY, 'size' => 13]);
        }
        $headerTable->addCell(8300)->addText(
            $payload['template']->header_text ?: 'Sistema de Gestão de Projetos de Software',
            ['size' => 8, 'color' => self::MUTED],
            ['alignment' => Jc::END],
        );

        $footer = $section->addFooter();
        $footer->addText(
            $payload['template']->footer_text ?: 'Documento gerado automaticamente pelo SGP',
            ['size' => 8, 'color' => self::MUTED],
            ['alignment' => Jc::CENTER],
        );
        $footer->addPreserveText(
            'Gerado por: '.$payload['generatedBy']->name
                .' | '.$payload['generatedAt']->format('d/m/Y H:i')
                .' | Página {PAGE} de {NUMPAGES}',
            ['size' => 8, 'color' => self::MUTED],
            ['alignment' => Jc::CENTER],
        );
    }

    /** @param array<string, mixed> $payload */
    private function addCover(Section $section, array $payload): void
    {
        $project = $payload['project'];
        $section->addTextBreak(5);
        $section->addText('SGP', ['size' => 15, 'bold' => true, 'color' => self::ACCENT], ['alignment' => Jc::CENTER]);
        $section->addText($payload['title'], ['size' => 24, 'bold' => true, 'color' => self::PRIMARY], ['alignment' => Jc::CENTER, 'spaceBefore' => 240]);
        $section->addText($project->name, ['size' => 16, 'color' => self::TEXT], ['alignment' => Jc::CENTER, 'spaceBefore' => 160]);
        $section->addText($project->code, ['size' => 11, 'bold' => true, 'color' => self::MUTED], ['alignment' => Jc::CENTER, 'spaceBefore' => 80]);
        $section->addTextBreak(5);
        $section->addText('Versão '.$payload['version'].'.0', ['size' => 10, 'color' => self::TEXT], ['alignment' => Jc::CENTER]);
        $section->addText($payload['generatedAt']->format('d/m/Y'), ['size' => 10, 'color' => self::TEXT], ['alignment' => Jc::CENTER]);
        $section->addPageBreak();
    }

    /** @param array<string, mixed> $payload */
    private function addVisionContent(Section $section, array $payload): void
    {
        $project = $payload['project'];
        $section->addTitle('1. Identificação do projeto', 1);
        $this->addKeyValueTable($section, [
            ['Projeto', $project->name],
            ['Código', $project->code],
            ['Cliente ou unidade', $project->client?->name ?? 'Sem demandante vinculado'],
            ['Responsável', $project->manager->name],
            ['Natureza da execução', $project->execution_nature->label()],
            ['Tratamento financeiro', $project->financial_management_mode->label()],
            ['Nível de gestão', $project->management_level->label()],
            ['Metodologia', $project->methodologyLabel()],
            ['Situação', $project->status->label()],
            ['Período previsto', $this->dateRange($project->start_date, $project->expected_end_date)],
        ]);

        $this->addTextSection($section, '2. Introdução', '2.1 Finalidade', 'Este documento apresenta a visão geral do projeto, seus objetivos, contexto, problema, solução proposta, público-alvo e limites de escopo. As informações foram consolidadas a partir dos registros mantidos no SGP.');
        $this->addTextSection($section, null, '2.2 Contexto', $project->document_context);
        $this->addTextSection($section, '3. Problema', null, $project->problem_statement);
        $this->addTextSection($section, '4. Solução proposta', null, $project->solution_summary);
        $this->addTextSection($section, '5. Objetivo geral', null, $project->objective);
        $this->addListSection($section, '6. Público-alvo e partes interessadas', $project->target_audience);
        $this->addListSection($section, '7. Escopo incluído', $project->scope_included);
        $this->addListSection($section, '8. Fora do escopo', $project->scope_excluded, 'Nenhum item informado.');
        $this->addListSection($section, '9. Premissas', $project->assumptions, 'Nenhuma premissa adicional informada.');
        $this->addListSection($section, '10. Restrições', $project->constraints, 'Nenhuma restrição adicional informada.');
        $this->addListSection($section, '11. Critérios de sucesso', $project->success_criteria, 'Nenhum critério adicional informado.');

        $section->addTitle('12. Equipe do projeto', 1);
        $table = $section->addTable('SgpTable');
        $table->addRow();
        foreach (['Participante', 'E-mail', 'Papel no projeto'] as $heading) {
            $table->addCell()->addText($heading, ['bold' => true, 'color' => 'FFFFFF']);
        }
        foreach ($payload['members'] as $member) {
            $table->addRow();
            $table->addCell()->addText($member['user']->name);
            $table->addCell()->addText($member['user']->email);
            $table->addCell()->addText($member['roles']);
        }

        $section->addTitle('13. Visão consolidada do escopo', 1);
        $section->addText('Requisitos cadastrados: '.$payload['requirements']->count().'. Tarefas cadastradas: '.$payload['tasks']->count().'.', [], ['spaceAfter' => 120]);
        $this->addTextSection($section, '14. Visão de futuro', null, $project->future_vision ?: 'Não informada.');
    }

    /** @param array<string, mixed> $payload */
    private function addRequirementsContent(Section $section, array $payload): void
    {
        $project = $payload['project'];
        $section->addTitle('1. Identificação', 1);
        $this->addKeyValueTable($section, [
            ['Projeto', $project->name],
            ['Código', $project->code],
            ['Responsável', $project->manager->name],
            ['Total de requisitos', (string) $payload['requirements']->count()],
        ]);
        $section->addTitle('2. Requisitos do projeto', 1);

        if ($payload['requirements']->isEmpty()) {
            $section->addText('Nenhum requisito cadastrado no projeto.');

            return;
        }

        foreach ($payload['requirements'] as $requirement) {
            $section->addTitle($requirement->code.' - '.$requirement->title, 2);
            $this->addKeyValueTable($section, [
                ['Tipo', $requirement->type->label()],
                ['Prioridade', $requirement->priority->label()],
                ['Situação', $requirement->status->label()],
                ['Responsável', $requirement->responsible?->name ?: 'Não definido'],
                ['Origem', $requirement->source ?: 'Não informada'],
                ['Versão', (string) $requirement->current_version],
            ]);
            $section->addText('Descrição', ['bold' => true, 'color' => self::TEXT], ['spaceBefore' => 100]);
            $section->addText($requirement->description);
            $section->addText('Critérios de aceite', ['bold' => true, 'color' => self::TEXT], ['spaceBefore' => 100]);
            $section->addText($requirement->acceptance_criteria ?: 'Não informados.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function addTasksContent(Section $section, array $payload): void
    {
        $project = $payload['project'];
        $section->addTitle('1. Identificação', 1);
        $this->addKeyValueTable($section, [
            ['Projeto', $project->name],
            ['Código', $project->code],
            ['Responsável', $project->manager->name],
            ['Total de tarefas', (string) $payload['tasks']->count()],
        ]);
        $section->addTitle('2. Tarefas do projeto', 1);

        if ($payload['tasks']->isEmpty()) {
            $section->addText('Nenhuma tarefa cadastrada no projeto.');

            return;
        }

        foreach ($payload['tasks'] as $task) {
            $section->addTitle($task->code.' - '.$task->title, 2);
            $this->addKeyValueTable($section, [
                ['Situação', $task->status->label()],
                ['Prioridade', $task->priority->label()],
                ['Responsável', $task->responsible?->name ?: 'Não definido'],
                ['Requisito', $task->requirement ? $task->requirement->code.' - '.$task->requirement->title : 'Não vinculado'],
                ['Tarefa principal', $task->parent ? $task->parent->code.' - '.$task->parent->title : 'Não se aplica'],
                ['Estimativa', $task->estimatedDuration() ?: 'Não informada'],
                ['Prazo', $task->due_date?->format('d/m/Y') ?: 'Não informado'],
            ]);
            $section->addText('Descrição', ['bold' => true, 'color' => self::TEXT], ['spaceBefore' => 100]);
            $section->addText($task->description ?: 'Não informada.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function addBacklogContent(Section $section, array $payload): void
    {
        $project = $payload['project'];
        $requirements = $payload['requirements'];
        $tasks = $payload['tasks'];

        $section->addTitle('1. Identificação', 1);
        $this->addKeyValueTable($section, [
            ['Projeto', $project->name],
            ['Código', $project->code],
            ['Responsável', $project->manager->name],
            ['Situação', $project->status->label()],
            ['Requisitos', (string) $requirements->count()],
            ['Tarefas', (string) $tasks->count()],
        ]);

        $section->addTitle('2. Backlog por requisito', 1);

        if ($requirements->isEmpty()) {
            $section->addText('Nenhum requisito cadastrado no projeto.');
        }

        foreach ($requirements as $requirement) {
            $section->addTitle($requirement->code.' - '.$requirement->title, 2);
            $section->addText(
                'Tipo: '.$requirement->type->label()
                    .' | Prioridade: '.$requirement->priority->label()
                    .' | Situação: '.$requirement->status->label(),
                ['size' => 9, 'color' => self::MUTED],
                ['spaceAfter' => 80],
            );

            $requirementTasks = $tasks->where('requirement_id', $requirement->id);
            $this->addCompactTaskTable($section, $requirementTasks);
        }

        $unlinkedTasks = $tasks->whereNull('requirement_id');
        $section->addTitle('3. Tarefas sem requisito vinculado', 1);
        $this->addCompactTaskTable($section, $unlinkedTasks);
    }

    private function addCompactTaskTable(Section $section, Collection $tasks): void
    {
        if ($tasks->isEmpty()) {
            $section->addText('Nenhuma tarefa nesta seção.', ['italic' => true, 'color' => self::MUTED]);

            return;
        }

        $table = $section->addTable('SgpTable');
        $table->addRow();
        foreach ([
            ['Tarefa', 3000],
            ['Situação', 1400],
            ['Prioridade', 1100],
            ['Responsável', 1800],
            ['Estimativa', 1000],
            ['Prazo', 1200],
        ] as [$heading, $width]) {
            $table->addCell($width)->addText($heading, ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);
        }

        foreach ($tasks as $task) {
            $table->addRow();
            $table->addCell(3000)->addText($task->code.' - '.$task->title, ['size' => 8]);
            $table->addCell(1400)->addText($task->status->label(), ['size' => 8]);
            $table->addCell(1100)->addText($task->priority->label(), ['size' => 8]);
            $table->addCell(1800)->addText($task->responsible?->name ?: 'Não definido', ['size' => 8]);
            $table->addCell(1000)->addText($task->estimatedDuration() ?: 'N/I', ['size' => 8]);
            $table->addCell(1200)->addText($task->due_date?->format('d/m/Y') ?: 'N/I', ['size' => 8]);
        }

        $section->addTextBreak();
    }

    /** @param array<int, array{0:string,1:string}> $rows */
    private function addKeyValueTable(Section $section, array $rows): void
    {
        $table = $section->addTable('SgpTable');
        foreach ($rows as [$label, $value]) {
            $table->addRow();
            $table->addCell(2500, ['bgColor' => self::LIGHT])->addText($label, ['bold' => true, 'color' => self::TEXT]);
            $table->addCell(7000)->addText($value);
        }
        $section->addTextBreak();
    }

    private function addTextSection(Section $section, ?string $heading1, ?string $heading2, ?string $text): void
    {
        if ($heading1) {
            $section->addTitle($heading1, 1);
        }
        if ($heading2) {
            $section->addTitle($heading2, 2);
        }
        $section->addText($text ?: 'Não informado.', [], ['alignment' => Jc::BOTH, 'spaceAfter' => 100]);
    }

    private function addListSection(Section $section, string $heading, ?string $text, string $empty = 'Não informado.'): void
    {
        $section->addTitle($heading, 1);
        $items = $this->lines($text);
        if ($items === []) {
            $section->addText($empty);

            return;
        }
        foreach ($items as $item) {
            $section->addListItem($item, 0, [], ['listType' => 3]);
        }
    }

    /** @return list<string> */
    private function lines(?string $text): array
    {
        return collect(preg_split('/\R/u', (string) $text))
            ->map(fn (string $line) => trim(preg_replace('/^[\s\-•]+/u', '', $line)))
            ->filter()
            ->values()
            ->all();
    }

    private function dateRange($start, $end): string
    {
        return ($start?->format('d/m/Y') ?: 'não informado').' a '.($end?->format('d/m/Y') ?: 'não informado');
    }

    /** @param array<string, mixed> $payload */
    private function writePdf(array $payload, string $path): void
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('documents.pdf', $payload)->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($path, $dompdf->output());
    }
}
