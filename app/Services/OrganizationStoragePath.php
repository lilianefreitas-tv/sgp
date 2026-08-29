<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\ProjectTestCase;
use App\Models\TestExecution;
use LogicException;

class OrganizationStoragePath
{
    public function __construct(private readonly OrganizationContext $context)
    {
    }

    public function attachments(Project $project): string
    {
        return $this->projectBase($project).'/attachments';
    }

    public function changeRequests(Project $project, ChangeRequest $changeRequest): string
    {
        if ($changeRequest->project_id !== $project->id
            || $changeRequest->organization_id !== $project->organization_id) {
            throw new LogicException('A solicitação de mudança não pertence ao projeto autorizado.');
        }

        return $this->attachments($project).'/change-requests/'.$changeRequest->id;
    }

    public function documents(Project $project, string $type): string
    {
        $safeType = preg_replace('/[^a-z0-9_-]+/i', '-', $type) ?: 'document';

        return $this->projectBase($project).'/generated-documents/'.$safeType;
    }

    public function testEvidence(Project $project, ProjectTestCase $case, TestExecution $execution): string
    {
        if ($case->project_id !== $project->id || $execution->test_case_id !== $case->id) {
            throw new LogicException('A execução de teste não pertence ao projeto autorizado.');
        }

        return $this->projectBase($project).'/tests/'.$case->id.'/executions/'.$execution->id;
    }

    public function projectBase(Project $project): string
    {
        $organizationId = (int) $project->organization_id;

        if ($organizationId < 1) {
            throw new LogicException('O projeto não possui organização para armazenamento.');
        }

        if ($this->context->active() && $this->context->id() !== $organizationId) {
            throw new LogicException('O projeto não pertence ao contexto organizacional ativo.');
        }

        return "organizations/{$organizationId}/projects/{$project->id}";
    }
}
