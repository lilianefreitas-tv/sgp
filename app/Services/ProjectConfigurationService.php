<?php

namespace App\Services;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\ProjectMethodology;
use App\Models\InitiativeConfigurationVersion;
use App\Models\Project;
use App\Models\ProjectConfigurationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProjectConfigurationService
{
    /** @var array<string, class-string> */
    private const DIMENSIONS = [
        'execution_nature' => ExecutionNature::class,
        'financial_management_mode' => FinancialManagementMode::class,
        'management_level' => ManagementLevel::class,
        'methodology' => ProjectMethodology::class,
    ];

    public function __construct(private OrganizationContext $context) {}

    public function recordInitial(Project $project, User $actor, string $justification): ProjectConfigurationVersion
    {
        if (blank(trim($justification))) {
            throw new LogicException('A justificativa é obrigatória.');
        }
        $this->authorize($project, $actor);

        return DB::transaction(function () use ($project, $actor, $justification): ProjectConfigurationVersion {
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            if ($locked->configurationVersions()->exists()) {
                throw new LogicException('O projeto já possui histórico de configuração.');
            }
            $this->assertValidSource($locked);

            return $this->version($locked, 1, $actor, $justification, now());
        });
    }

    public function change(Project $project, array $dimensions, User $actor, string $justification): ProjectConfigurationVersion
    {
        if (blank(trim($justification))) {
            throw new LogicException('A justificativa é obrigatória.');
        }
        $dimensions = $this->normalizeDimensions($dimensions);
        $this->authorize($project, $actor);

        return DB::transaction(function () use ($project, $dimensions, $actor, $justification): ProjectConfigurationVersion {
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $current = $locked->configurationVersions()->whereNull('superseded_at')->lockForUpdate()->first();
            $now = now();
            if ($current) {
                $current->supersede($now);
            }
            $locked->update($dimensions);
            $this->assertValidSource($locked);

            return $this->version($locked, ($current?->sequence ?? 0) + 1, $actor, $justification, $now);
        });
    }

    private function version(Project $project, int $sequence, User $actor, string $justification, $now): ProjectConfigurationVersion
    {
        return $project->configurationVersions()->create(['sequence' => $sequence, 'execution_nature' => $project->execution_nature,
            'financial_management_mode' => $project->financial_management_mode, 'management_level' => $project->management_level,
            'methodology' => $project->methodology, 'source_initiative_configuration_version_id' => $project->source_initiative_configuration_version_id,
            'effective_from' => $now, 'changed_by' => $actor->id, 'justification' => $justification, 'recorded_at' => $now]);
    }

    /** @return array<string, mixed> */
    private function normalizeDimensions(array $dimensions): array
    {
        if ($dimensions === [] || array_diff(array_keys($dimensions), array_keys(self::DIMENSIONS)) !== []) {
            throw new LogicException('A operação recebeu campos não permitidos.');
        }

        foreach ($dimensions as $field => $value) {
            $enum = self::DIMENSIONS[$field];
            $case = $value instanceof $enum ? $value : (is_string($value) ? $enum::tryFrom($value) : null);
            if ($case === null || $case === ManagementLevel::Simplified) {
                throw new LogicException("Valor inválido para {$field}.");
            }
            $dimensions[$field] = $case;
        }

        return $dimensions;
    }

    private function assertValidSource(Project $project): void
    {
        if ($project->source_initiative_configuration_version_id !== null
            && ! InitiativeConfigurationVersion::query()->whereKey($project->source_initiative_configuration_version_id)
                ->where('organization_id', $project->organization_id)->exists()) {
            throw new LogicException('A versão de origem não pertence à organização do projeto.');
        }
    }

    private function authorize(Project $project, User $actor): void
    {
        if (! $actor->exists || ! $actor->is_active) {
            throw new LogicException('O ator deve existir e estar ativo.');
        }
        if (! $this->context->active() || $this->context->id() !== (int) $project->organization_id) {
            throw new LogicException('O projeto não pertence ao contexto ativo.');
        }
        if ($actor->isSuperAdmin() && $this->context->isPlatformAccess()) {
            return;
        }
        if (! $actor->organizationMemberships()->where('organization_id', $project->organization_id)
            ->where('status', OrganizationMembershipStatus::Active->value)->exists()) {
            throw new LogicException('O ator não possui vínculo organizacional ativo.');
        }
    }
}
