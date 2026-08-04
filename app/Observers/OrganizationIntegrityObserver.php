<?php

namespace App\Observers;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Services\OrganizationContext;
use App\Services\ProjectCodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

class OrganizationIntegrityObserver
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly ProjectCodeGenerator $projectCodes,
    ) {}

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
        $organizationId = null;

        if ($this->context->active()) {
            $providedOrganizationId = $model->getAttribute('organization_id');

            if (filled($providedOrganizationId)
                && (int) $providedOrganizationId !== $this->context->id()) {
                throw new LogicException(
                    'A organização do registro não corresponde ao contexto autorizado.'
                );
            }

            $organizationId = $this->context->id();
        } elseif (filled($model->getAttribute('organization_id'))) {
            $organizationId = (int) $model->getAttribute('organization_id');
        } elseif ($model instanceof Client
            || $model instanceof DocumentTemplate
            || ($model instanceof Project && blank($model->getAttribute('client_id')))) {
            $organizationId = $this->resolveRootOrganizationId();
        } else {
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
        }

        $model->setAttribute('organization_id', (int) $organizationId);

        if ($model instanceof Project && blank($model->getAttribute('code'))) {
            $model->setAttribute('code', $this->projectCodes->next((int) $organizationId));
        }
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
