<?php

namespace App\Services;

use App\Enums\TestCaseStatus;
use App\Enums\TestExecutionResult;
use App\Models\Project;
use App\Models\ProjectTestCase;
use Illuminate\Support\Collection;

class ProjectTraceabilityService
{
    public function summary(Project $project): array
    {
        $requirements = $project->requirements()->where('is_active', true)
            ->withCount([
                'tasks as active_tasks_count' => fn ($query) => $query->where('is_active', true),
                'testCases as test_cases_count',
            ])->get();
        $cases = $project->testCases()->with('executions.evidences')->get();
        $ready = $cases->where('status', TestCaseStatus::Ready);
        $executed = $ready->filter(fn (ProjectTestCase $case) => $case->latestExecution() !== null);
        $evidenced = $executed->filter(fn (ProjectTestCase $case) => $case->latestExecution()?->evidences->isNotEmpty());
        $passed = $ready->filter(fn (ProjectTestCase $case) => $case->latestExecution()?->result === TestExecutionResult::Passed);

        return [
            'requirements' => $requirements->count(),
            'requirements_with_tasks' => $requirements->where('active_tasks_count', '>', 0)->count(),
            'requirements_with_tests' => $requirements->where('test_cases_count', '>', 0)->count(),
            'ready_cases' => $ready->count(),
            'executed_cases' => $executed->count(),
            'evidenced_cases' => $evidenced->count(),
            'passed_cases' => $passed->count(),
            'homologations' => $project->homologations()->count(),
            'requirement_work_coverage' => $this->percentage($requirements->where('active_tasks_count', '>', 0)->count(), $requirements->count()),
            'requirement_test_coverage' => $this->percentage($requirements->where('test_cases_count', '>', 0)->count(), $requirements->count()),
            'execution_coverage' => $this->percentage($executed->count(), $ready->count()),
            'evidence_coverage' => $this->percentage($evidenced->count(), $executed->count()),
            'gap_count' => $requirements->where('active_tasks_count', 0)->count()
                + $requirements->where('test_cases_count', 0)->count()
                + ($ready->count() - $executed->count())
                + ($executed->count() - $evidenced->count()),
        ];
    }

    public function matrix(Project $project): array
    {
        $project->loadMissing(['initiative', 'contracts', 'baselines', 'changeRequests.currentImpactAnalysis', 'homologations']);
        $requirements = $project->requirements()->where('is_active', true)
            ->with([
                'tasks' => fn ($query) => $query->where('is_active', true)->orderBy('code'),
                'testCases.executions.evidences',
            ])->orderBy('code')->get();
        $cases = $project->testCases()->with(['requirement', 'changeRequest', 'baseline', 'executions.evidences'])->get();
        $summary = $this->summaryFromCollections($project, $requirements, $cases);

        $requirementRows = $requirements->map(function ($requirement): array {
            $executions = $requirement->testCases->map(fn (ProjectTestCase $case) => $case->latestExecution())->filter();
            $latest = $executions->sortByDesc('executed_at')->first();
            $gaps = collect();
            if ($requirement->tasks->isEmpty()) $gaps->push('Sem tarefa vinculada');
            if ($requirement->testCases->isEmpty()) $gaps->push('Sem caso de teste');
            if ($requirement->testCases->isNotEmpty() && $executions->isEmpty()) $gaps->push('Teste não executado');
            if ($executions->isNotEmpty() && $executions->sum(fn ($execution) => $execution->evidences->count()) === 0) $gaps->push('Sem evidência');

            return [
                'requirement' => $requirement,
                'tasks' => $requirement->tasks,
                'test_cases' => $requirement->testCases,
                'latest_execution' => $latest,
                'evidence_count' => $executions->sum(fn ($execution) => $execution->evidences->count()),
                'gaps' => $gaps,
            ];
        });

        $caseRows = $cases->map(function (ProjectTestCase $case): array {
            $latest = $case->latestExecution();
            $source = $case->requirement ?? $case->changeRequest ?? $case->baseline;
            $gaps = collect();
            if ($source === null) $gaps->push('Sem origem vinculada');
            if ($case->status === TestCaseStatus::Ready && $latest === null) $gaps->push('Não executado');
            if ($latest !== null && $latest->result !== TestExecutionResult::Passed) $gaps->push('Resultado não aprovado');
            if ($latest !== null && $latest->evidences->isEmpty()) $gaps->push('Sem evidência');

            return [
                'case' => $case,
                'source' => $source,
                'source_label' => $case->requirement?->code
                    ?? $case->changeRequest?->code
                    ?? ($case->baseline ? 'Baseline v'.$case->baseline->version : 'Não vinculada'),
                'latest_execution' => $latest,
                'evidence_count' => $latest?->evidences->count() ?? 0,
                'gaps' => $gaps,
            ];
        });

        return [
            'summary' => $summary,
            'requirements' => $requirementRows,
            'test_cases' => $caseRows,
            'origin' => [
                'initiative' => $project->initiative,
                'contracts' => $project->contracts,
                'baselines' => $project->baselines,
                'changes' => $project->changeRequests,
                'homologations' => $project->homologations,
            ],
            'mps' => $this->mpsSupport($project, $summary),
        ];
    }

    private function summaryFromCollections(Project $project, Collection $requirements, Collection $cases): array
    {
        $ready = $cases->where('status', TestCaseStatus::Ready);
        $executed = $ready->filter(fn (ProjectTestCase $case) => $case->latestExecution() !== null);
        $evidenced = $executed->filter(fn (ProjectTestCase $case) => $case->latestExecution()?->evidences->isNotEmpty());
        $passed = $ready->filter(fn (ProjectTestCase $case) => $case->latestExecution()?->result === TestExecutionResult::Passed);
        $withTasks = $requirements->filter(fn ($requirement) => $requirement->tasks->isNotEmpty());
        $withTests = $requirements->filter(fn ($requirement) => $requirement->testCases->isNotEmpty());

        return [
            'requirements' => $requirements->count(), 'requirements_with_tasks' => $withTasks->count(),
            'requirements_with_tests' => $withTests->count(), 'ready_cases' => $ready->count(),
            'executed_cases' => $executed->count(), 'evidenced_cases' => $evidenced->count(),
            'passed_cases' => $passed->count(), 'homologations' => $project->homologations->count(),
            'requirement_work_coverage' => $this->percentage($withTasks->count(), $requirements->count()),
            'requirement_test_coverage' => $this->percentage($withTests->count(), $requirements->count()),
            'execution_coverage' => $this->percentage($executed->count(), $ready->count()),
            'evidence_coverage' => $this->percentage($evidenced->count(), $executed->count()),
            'gap_count' => ($requirements->count() - $withTasks->count()) + ($requirements->count() - $withTests->count())
                + ($ready->count() - $executed->count()) + ($executed->count() - $evidenced->count()),
        ];
    }

    private function mpsSupport(Project $project, array $summary): array
    {
        $counts = [
            'tasks' => $project->tasks()->where('is_active', true)->count(),
            'baselines' => $project->baselines->count(), 'changes' => $project->changeRequests->count(),
            'analysed_changes' => $project->changeRequests->whereNotNull('currentImpactAnalysis')->count(),
            'documents' => $project->documents()->count(), 'contracts' => $project->contracts->count(),
            'configurations' => $project->configurationVersions()->count(),
        ];

        return [
            $this->process('GPR', 'Gerência de Projetos', 'Escopo, planejamento, trabalho, baselines e mudanças.',
                "{$summary['requirements']} requisito(s), {$counts['tasks']} tarefa(s), {$counts['baselines']} baseline(s)",
                $summary['requirements'] > 0 && $counts['tasks'] > 0 ? 'supported' : 'partial',
                $summary['requirements'] > 0 && $counts['tasks'] > 0 ? 'Revisão humana do planejamento permanece necessária.' : 'Planejamento ou decomposição do trabalho incompleto.'),
            $this->process('REQ', 'Engenharia de Requisitos', 'Requisitos, versões, critérios, vínculos e cobertura por testes.',
                "Cobertura de testes: ".$this->displayPercentage($summary['requirement_test_coverage']),
                $summary['requirements'] === 0 ? 'missing' : ($summary['requirement_test_coverage'] === 100 ? 'supported' : 'partial'),
                $summary['requirements'] === 0 ? 'Nenhum requisito ativo.' : 'Validação e aprovação dos requisitos são responsabilidades organizacionais.'),
            $this->process('GCO', 'Gerência de Configuração', 'Baselines, documentos, contratos, versões e integridade.',
                "{$counts['baselines']} baseline(s), {$counts['documents']} documento(s), {$counts['contracts']} contrato(s)",
                $counts['baselines'] > 0 ? 'supported' : 'partial',
                $counts['baselines'] > 0 ? 'A disciplina sobre itens de configuração permanece organizacional.' : 'Nenhuma baseline constituída.'),
            $this->process('VV', 'Verificação e Validação', 'Casos, execuções, evidências e homologações.',
                "{$summary['executed_cases']}/{$summary['ready_cases']} executados, {$summary['evidenced_cases']} evidenciados, {$summary['homologations']} decisão(ões)",
                $summary['ready_cases'] > 0 && $summary['execution_coverage'] === 100 && $summary['evidence_coverage'] === 100 ? 'supported' : 'partial',
                $summary['ready_cases'] === 0 ? 'Nenhum caso pronto para execução.' : 'Competência, independência e critérios de aceite pertencem à organização.'),
            $this->process('GDE', 'Gerência de Decisões', 'Mudanças, alternativas, impactos e decisões auditadas.',
                "{$counts['analysed_changes']}/{$counts['changes']} mudança(s) com análise corrente",
                $counts['changes'] === 0 ? 'contextual' : ($counts['analysed_changes'] === $counts['changes'] ? 'supported' : 'partial'),
                $counts['changes'] === 0 ? 'Sem mudança registrada no recorte atual.' : 'O método decisório precisa ser institucionalizado.'),
            $this->process('MED', 'Medição', 'Contagens e indicadores de cobertura calculados a partir dos dados do projeto.',
                'Cobertura de trabalho '.$this->displayPercentage($summary['requirement_work_coverage']).'; testes '.$this->displayPercentage($summary['requirement_test_coverage']),
                $summary['requirements'] > 0 ? 'partial' : 'missing',
                'As medidas precisam de definição operacional, meta e rotina de análise da organização.'),
            $this->process('GPC', 'Gerência de Processos', 'Configuração adaptativa, níveis de gestão e histórico de configuração.',
                "{$counts['configurations']} versão(ões) de configuração do projeto",
                $counts['configurations'] > 0 ? 'supported' : 'partial',
                'O processo padrão e sua adaptação pertencem à organização.'),
            $this->process('AQU', 'Aquisição', 'Contratos, versões, aditivos e aceite quando aplicáveis.',
                $counts['contracts'] > 0 ? "{$counts['contracts']} contrato(s) vinculado(s)" : 'Sem contrato no contexto atual',
                $counts['contracts'] > 0 ? 'supported' : 'contextual',
                $counts['contracts'] > 0 ? 'Desempenho do fornecedor e critérios contratuais exigem gestão organizacional.' : 'Processo contextual, aplicável somente quando houver aquisição ou fornecimento.'),
        ];
    }

    private function process(string $code, string $name, string $capacity, string $evidence, string $status, string $gap): array
    {
        return compact('code', 'name', 'capacity', 'evidence', 'status', 'gap');
    }

    private function percentage(int $part, int $total): ?int
    {
        return $total === 0 ? null : (int) round(($part / $total) * 100);
    }

    private function displayPercentage(?int $value): string
    {
        return $value === null ? 'N/A' : $value.'%';
    }
}
