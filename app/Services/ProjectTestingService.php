<?php

namespace App\Services;

use App\Enums\HomologationStatus;
use App\Enums\ProjectRole;
use App\Enums\TestCaseSeverity;
use App\Enums\TestCaseStatus;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectHomologation;
use App\Models\ProjectTestCase;
use App\Models\TestExecution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectTestingService
{
    public function createCase(Project $project, array $data, User $actor): ProjectTestCase
    {
        return DB::transaction(function () use ($project, $data, $actor): ProjectTestCase {
            $this->lockProject($project);
            $sequence = (int) $project->testCases()->max('sequence') + 1;
            $case = $project->testCases()->create($this->casePayload($project, $data) + [
                'organization_id' => $project->organization_id,
                'sequence' => $sequence,
                'code' => 'CT-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            ProjectActivity::record($project, $actor, 'test_case_created', 'Caso de teste registrado', 'test_case', $case->id, [
                'details' => $case->code.' · '.$case->title,
            ]);
            return $case;
        });
    }

    public function updateCase(ProjectTestCase $case, array $data, User $actor): ProjectTestCase
    {
        return DB::transaction(function () use ($case, $data, $actor): ProjectTestCase {
            $locked = ProjectTestCase::query()->lockForUpdate()->findOrFail($case->id);
            $locked->update($this->casePayload($locked->project, $data) + ['updated_by' => $actor->id]);
            ProjectActivity::record($locked->project, $actor, 'test_case_updated', 'Caso de teste atualizado', 'test_case', $locked->id, [
                'details' => $locked->code.' · '.$locked->title,
            ]);
            return $locked->fresh();
        });
    }

    public function execute(ProjectTestCase $case, array $data, User $actor): TestExecution
    {
        return DB::transaction(function () use ($case, $data, $actor): TestExecution {
            $locked = ProjectTestCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($locked->status !== TestCaseStatus::Ready) {
                throw ValidationException::withMessages(['status' => 'Somente casos prontos podem ser executados.']);
            }
            if ($locked->assigned_tester_id !== null && $locked->assigned_tester_id !== $actor->id) {
                throw ValidationException::withMessages(['executor' => 'Este caso está designado para outro Testador.']);
            }
            $number = (int) $locked->executions()->max('execution_number') + 1;
            $execution = $locked->executions()->create([
                'organization_id' => $locked->organization_id,
                'execution_number' => $number,
                'result' => $data['result'],
                'case_snapshot' => [
                    'code' => $locked->code,
                    'title' => $locked->title,
                    'objective' => $locked->objective,
                    'preconditions' => $locked->preconditions,
                    'test_data' => $locked->test_data,
                    'steps' => $locked->steps,
                    'expected_result' => $locked->expected_result,
                    'severity' => $locked->severity->value,
                    'requirement_id' => $locked->requirement_id,
                    'change_request_id' => $locked->change_request_id,
                    'baseline_id' => $locked->baseline_id,
                ],
                'environment' => trim($data['environment']),
                'observed_result' => trim($data['observed_result']),
                'notes' => $this->nullableText($data['notes'] ?? null),
                'defect_reference' => $this->nullableText($data['defect_reference'] ?? null),
                'executed_by' => $actor->id,
                'executed_at' => now(),
            ]);
            ProjectActivity::record($locked->project, $actor, 'test_executed', 'Caso de teste executado', 'test_case', $locked->id, [
                'details' => $locked->code.' · execução '.$number.' · '.$execution->result->label(),
            ]);
            return $execution;
        });
    }

    public function homologate(Project $project, array $data, User $actor): ProjectHomologation
    {
        return DB::transaction(function () use ($project, $data, $actor): ProjectHomologation {
            $this->lockProject($project);
            $cases = $project->testCases()->where('status', TestCaseStatus::Ready->value)
                ->with(['executions.evidences'])->get();
            if ($cases->isEmpty()) {
                throw ValidationException::withMessages(['status' => 'Cadastre ao menos um caso pronto antes da homologação.']);
            }

            $summary = ['total' => $cases->count(), 'passed' => 0, 'failed' => 0, 'blocked' => 0, 'not_executed' => 0, 'without_evidence' => 0, 'items' => []];
            $criticalOrHighOpen = false;
            foreach ($cases as $case) {
                $latest = $case->executions->sortByDesc('execution_number')->first();
                if ($latest === null) {
                    $summary['not_executed']++;
                    $criticalOrHighOpen = $criticalOrHighOpen || in_array($case->severity, [TestCaseSeverity::Critical, TestCaseSeverity::High], true);
                    $summary['items'][] = [
                        'test_case_id' => $case->id, 'code' => $case->code,
                        'severity' => $case->severity->value, 'execution_id' => null,
                        'result' => 'not_executed', 'evidences' => 0,
                    ];
                    continue;
                }
                $summary[$latest->result->value]++;
                if ($latest->evidences->isEmpty()) {
                    $summary['without_evidence']++;
                    if (in_array($case->severity, [TestCaseSeverity::Critical, TestCaseSeverity::High], true)) {
                        $criticalOrHighOpen = true;
                    }
                }
                $summary['items'][] = [
                    'test_case_id' => $case->id, 'code' => $case->code,
                    'severity' => $case->severity->value, 'execution_id' => $latest->id,
                    'result' => $latest->result->value, 'evidences' => $latest->evidences->count(),
                ];
                if ($latest->result->value !== 'passed' && in_array($case->severity, [TestCaseSeverity::Critical, TestCaseSeverity::High], true)) {
                    $criticalOrHighOpen = true;
                }
            }

            $status = HomologationStatus::from($data['status']);
            if ($status === HomologationStatus::Approved
                && ($summary['passed'] !== $summary['total'] || $summary['without_evidence'] > 0)) {
                throw ValidationException::withMessages(['status' => 'A aprovação exige todos os casos prontos aprovados e com evidência.']);
            }
            if ($status === HomologationStatus::ApprovedWithReservations && $criticalOrHighOpen) {
                throw ValidationException::withMessages(['status' => 'Falhas críticas ou altas não podem ser homologadas com ressalvas.']);
            }

            $sequence = (int) $project->homologations()->max('sequence') + 1;
            $homologation = $project->homologations()->create([
                'organization_id' => $project->organization_id,
                'sequence' => $sequence,
                'code' => 'HOM-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                'title' => trim($data['title']),
                'status' => $status,
                'baseline_id' => $this->projectEntityId($project, 'baselines', $data['baseline_id'] ?? null, 'baseline_id'),
                'commit_reference' => $this->nullableText($data['commit_reference'] ?? null),
                'environment' => trim($data['environment']),
                'scope' => trim($data['scope']),
                'decision_notes' => trim($data['decision_notes']),
                'summary' => $summary,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);
            ProjectActivity::record($project, $actor, 'project_homologated', 'Decisão de homologação registrada', 'homologation', $homologation->id, [
                'details' => $homologation->code.' · '.$homologation->status->label(),
            ]);
            return $homologation;
        });
    }

    private function casePayload(Project $project, array $data): array
    {
        return [
            'title' => trim($data['title']), 'objective' => trim($data['objective']),
            'preconditions' => $this->nullableText($data['preconditions'] ?? null),
            'test_data' => $this->nullableText($data['test_data'] ?? null),
            'steps' => trim($data['steps']), 'expected_result' => trim($data['expected_result']),
            'severity' => $data['severity'], 'status' => $data['status'],
            'assigned_tester_id' => $this->testerId($project, $data['assigned_tester_id'] ?? null),
            'requirement_id' => $this->projectEntityId($project, 'requirements', $data['requirement_id'] ?? null, 'requirement_id'),
            'change_request_id' => $this->projectEntityId($project, 'changeRequests', $data['change_request_id'] ?? null, 'change_request_id'),
            'baseline_id' => $this->projectEntityId($project, 'baselines', $data['baseline_id'] ?? null, 'baseline_id'),
        ];
    }

    private function testerId(Project $project, mixed $value): ?int
    {
        if (blank($value)) return null;
        $id = (int) $value;
        $exists = $project->memberships()->where('user_id', $id)->where('role', ProjectRole::Tester->value)->where('is_active', true)->exists();
        if (! $exists) throw ValidationException::withMessages(['assigned_tester_id' => 'Selecione um Testador ativo deste projeto.']);
        return $id;
    }

    private function projectEntityId(Project $project, string $relation, mixed $value, string $field): ?int
    {
        if (blank($value)) return null;
        $id = (int) $value;
        if (! $project->{$relation}()->whereKey($id)->exists()) {
            throw ValidationException::withMessages([$field => 'O vínculo selecionado não pertence a este projeto.']);
        }
        return $id;
    }

    private function lockProject(Project $project): void
    {
        DB::table('projects')->where('id', $project->id)->where('organization_id', $project->organization_id)->lockForUpdate()->firstOrFail();
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
