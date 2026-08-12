<?php

namespace App\Observers;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Client;
use App\Models\ApplicabilityDecision;
use App\Models\DocumentTemplate;
use App\Models\Initiative;
use App\Models\InitiativeConfigurationVersion;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanTaskPosition;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectAttachment;
use App\Models\ProjectComment;
use App\Models\ProjectConfigurationVersion;
use App\Models\ProjectDocument;
use App\Models\ProjectMembership;
use App\Models\Requirement;
use App\Models\RequirementDependency;
use App\Models\RequirementVersion;
use App\Models\Task;
use App\Models\TaskHistory;
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
        Project::class => ['clients', 'client_id'],
        ProjectMembership::class => ['projects', 'project_id'],
        Requirement::class => ['projects', 'project_id'],
        RequirementVersion::class => ['requirements', 'requirement_id'],
        RequirementDependency::class => ['requirements', 'requirement_id'],
        Task::class => ['projects', 'project_id'],
        TaskHistory::class => ['tasks', 'task_id'],
        KanbanBoard::class => ['projects', 'project_id'],
        KanbanColumn::class => ['kanban_boards', 'kanban_board_id'],
        KanbanTaskPosition::class => ['tasks', 'task_id'],
        ProjectDocument::class => ['projects', 'project_id'],
        ProjectComment::class => ['projects', 'project_id'],
        ProjectAttachment::class => ['projects', 'project_id'],
        ProjectActivity::class => ['projects', 'project_id'],
        InitiativeConfigurationVersion::class => ['initiatives', 'initiative_id'],
        ProjectConfigurationVersion::class => ['projects', 'project_id'],
        ApplicabilityDecision::class => ['projects', 'project_id'],
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
            || $model instanceof Initiative
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
