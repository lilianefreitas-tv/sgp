<?php

namespace App\Observers;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Client;
use App\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

class OrganizationIntegrityObserver
{
    /** @var array<class-string<Model>, array{0: string, 1: string}> */
    private const PARENT_RELATIONS = [
        \App\Models\Project::class => ['clients', 'client_id'],
        \App\Models\ProjectMembership::class => ['projects', 'project_id'],
        \App\Models\Requirement::class => ['projects', 'project_id'],
        \App\Models\RequirementVersion::class => ['requirements', 'requirement_id'],
        \App\Models\RequirementDependency::class => ['requirements', 'requirement_id'],
        \App\Models\Task::class => ['projects', 'project_id'],
        \App\Models\TaskHistory::class => ['tasks', 'task_id'],
        \App\Models\KanbanBoard::class => ['projects', 'project_id'],
        \App\Models\KanbanColumn::class => ['kanban_boards', 'kanban_board_id'],
        \App\Models\KanbanTaskPosition::class => ['tasks', 'task_id'],
        \App\Models\ProjectDocument::class => ['projects', 'project_id'],
        \App\Models\ProjectComment::class => ['projects', 'project_id'],
        \App\Models\ProjectAttachment::class => ['projects', 'project_id'],
        \App\Models\ProjectActivity::class => ['projects', 'project_id'],
    ];

    public function creating(Model $model): void
    {
        if (filled($model->getAttribute('organization_id'))) {
            return;
        }

        if ($model instanceof Client || $model instanceof DocumentTemplate) {
            $model->setAttribute('organization_id', $this->resolveRootOrganizationId());

            return;
        }

        $relation = self::PARENT_RELATIONS[$model::class] ?? null;

        if ($relation === null) {
            throw new LogicException('Não foi definida uma origem organizacional para '.class_basename($model).'.');
        }

        [$parentTable, $foreignKey] = $relation;
        $parentId = $model->getAttribute($foreignKey);

        if (blank($parentId)) {
            throw new LogicException("O campo {$foreignKey} é obrigatório para derivar a organização.");
        }

        $organizationId = DB::table($parentTable)
            ->where('id', $parentId)
            ->value('organization_id');

        if (blank($organizationId)) {
            throw new LogicException("Não foi possível derivar a organização de {$parentTable}#{$parentId}.");
        }

        $model->setAttribute('organization_id', (int) $organizationId);
    }

    private function resolveRootOrganizationId(): int
    {
        $user = Auth::user();

        if ($user !== null) {
            $membership = $user->organizationMemberships()
                ->where('status', OrganizationMembershipStatus::Active->value)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            if ($membership !== null) {
                return (int) $membership->organization_id;
            }
        }

        $organizationIds = DB::table('organizations')
            ->where('status', OrganizationStatus::Active->value)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id');

        if ($organizationIds->count() === 1) {
            return (int) $organizationIds->first();
        }

        throw new LogicException(
            'Não há uma única organização ativa que permita determinar o contexto da gravação.'
        );
    }
}
