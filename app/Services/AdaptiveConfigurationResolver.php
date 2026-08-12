<?php

namespace App\Services;

use App\Enums\ApplicabilityTargetType;
use App\Models\Initiative;
use App\Models\Project;
use App\ValueObjects\ApplicabilityContext;
use DateTimeInterface;
use LogicException;

class AdaptiveConfigurationResolver
{
    public function initiative(Initiative $initiative, ApplicabilityTargetType $type, string $key, DateTimeInterface $at, string $ruleSetVersion): ApplicabilityContext
    {
        return $this->context($initiative, 'initiative', $type, $key, $at, $ruleSetVersion, ['origin', 'execution_nature', 'financial_management_mode', 'management_level', 'methodology'], $initiative->state->value);
    }
    public function project(Project $project, ApplicabilityTargetType $type, string $key, DateTimeInterface $at, string $ruleSetVersion): ApplicabilityContext
    {
        return $this->context($project, 'project', $type, $key, $at, $ruleSetVersion, ['execution_nature', 'financial_management_mode', 'management_level', 'methodology'], $project->status->value);
    }
    private function context(object $subject, string $type, ApplicabilityTargetType $targetType, string $key, DateTimeInterface $at, string $ruleSetVersion, array $dimensions, string $state): ApplicabilityContext
    {
        $versions = $subject->configurationVersions()->withoutGlobalScopes()->where('organization_id', $subject->organization_id)
            ->where('effective_from', '<=', $at)->where(fn ($q) => $q->whereNull('superseded_at')->orWhere('superseded_at', '>', $at))->get();
        if ($versions->count() !== 1) throw new LogicException($versions->isEmpty() ? 'Não há configuração vigente no instante avaliado.' : 'Há sobreposição de versões de configuração.');
        $version = $versions->first();
        return new ApplicabilityContext($subject->organization_id, $type, $subject->id, $state, $version->id,
            collect($dimensions)->mapWithKeys(fn ($field) => [$field => $version->{$field}->value])->all(), $targetType, $key, $at, $ruleSetVersion);
    }
}
