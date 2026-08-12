<?php

namespace App\Services;

use App\Enums\ArtifactType;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Artifact;
use App\Models\ArtifactRevision;
use App\Models\Initiative;
use App\Models\InitiativeConfigurationVersion;
use App\Models\Project;
use App\Models\ProjectConfigurationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ArtifactRevisionService
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly ArtifactCodeGenerator $codes,
        private readonly ArtifactSnapshotCanonicalizer $canonicalizer,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Artifact
    {
        $this->rejectUnknownCreateFields($attributes);

        return DB::transaction(function () use ($attributes, $actor): Artifact {
            $organizationId = $this->authorize($actor);
            [$initiative, $project] = $this->resolveParent($attributes, $organizationId);
            $type = $this->resolveType($attributes['type'] ?? null, $initiative, $project);
            $content = $this->canonicalizer->canonicalize($this->arrayValue($attributes['content'] ?? null, 'content'));
            $metadata = array_key_exists('metadata', $attributes) && $attributes['metadata'] !== null
                ? $this->canonicalizer->canonicalize($this->arrayValue($attributes['metadata'], 'metadata')) : null;

            $artifact = Artifact::query()->create([
                'organization_id' => $organizationId,
                'initiative_id' => $initiative?->id,
                'project_id' => $project?->id,
                'code' => $this->codes->next($organizationId),
                'type' => $type,
                'title' => $this->requiredText($attributes['title'] ?? null, 'title', 255),
                'description' => $this->nullableText($attributes['description'] ?? null, 10000),
                'created_by' => $actor->id,
            ]);

            $this->appendLocked($artifact, $content, $metadata, (int) ($attributes['schema_version'] ?? 1), $this->requiredText($attributes['change_reason'] ?? 'Registro inicial.', 'change_reason', 10000), $actor, $initiative, $project);

            return $artifact->fresh(['revisions']) ?? $artifact;
        });
    }

    /** @param array<mixed> $content @param array<mixed>|null $metadata */
    public function revise(Artifact $artifact, array $content, ?array $metadata, int $schemaVersion, string $reason, User $actor): ArtifactRevision
    {
        return DB::transaction(function () use ($artifact, $content, $metadata, $schemaVersion, $reason, $actor): ArtifactRevision {
            $organizationId = $this->authorize($actor, $artifact->organization_id);
            $locked = Artifact::query()->where('organization_id', $organizationId)->lockForUpdate()->find($artifact->id);
            if ($locked === null) {
                throw new LogicException('Artefato não encontrado no contexto organizacional ativo.');
            }
            if ($locked->archived_at !== null) {
                throw new LogicException('Artefatos arquivados não aceitam novas revisões.');
            }
            $initiative = $locked->initiative_id === null ? null : Initiative::query()->where('organization_id', $organizationId)->lockForUpdate()->find($locked->initiative_id);
            $project = $locked->project_id === null ? null : Project::query()->where('organization_id', $organizationId)->lockForUpdate()->find($locked->project_id);

            return $this->appendLocked(
                $locked,
                $this->canonicalizer->canonicalize($content),
                $metadata === null ? null : $this->canonicalizer->canonicalize($metadata),
                $schemaVersion,
                $this->requiredText($reason, 'change_reason', 10000),
                $actor,
                $initiative,
                $project,
            );
        });
    }

    public function archive(Artifact $artifact, string $reason, User $actor): Artifact
    {
        return DB::transaction(function () use ($artifact, $reason, $actor): Artifact {
            $organizationId = $this->authorize($actor, $artifact->organization_id);
            $locked = Artifact::query()->where('organization_id', $organizationId)->lockForUpdate()->find($artifact->id);
            if ($locked === null) {
                throw new LogicException('Artefato não encontrado no contexto organizacional ativo.');
            }
            $this->requiredText($reason, 'archive_reason', 10000);
            if ($locked->archived_at === null) {
                $locked->update(['archived_at' => now()]);
            }

            return $locked;
        });
    }

    /** @param array<string, mixed> $attributes @return array{0: ?Initiative, 1: ?Project} */
    private function resolveParent(array $attributes, int $organizationId): array
    {
        $initiativeId = $attributes['initiative_id'] ?? null;
        $projectId = $attributes['project_id'] ?? null;
        if (($initiativeId === null) === ($projectId === null)) {
            throw new LogicException('Um artefato deve pertencer a exatamente uma iniciativa ou projeto.');
        }
        $initiative = $initiativeId === null ? null : Initiative::query()->where('organization_id', $organizationId)->lockForUpdate()->find($initiativeId);
        $project = $projectId === null ? null : Project::query()->where('organization_id', $organizationId)->lockForUpdate()->find($projectId);
        if (($initiativeId !== null && $initiative === null) || ($projectId !== null && $project === null)) {
            throw new LogicException('O pai informado não pertence à organização ativa.');
        }

        return [$initiative, $project];
    }

    private function resolveType(mixed $value, ?Initiative $initiative, ?Project $project): ArtifactType
    {
        $type = $value instanceof ArtifactType ? $value : ArtifactType::tryFrom((string) $value);
        if ($type === null) {
            throw new LogicException('O tipo de artefato é inválido.');
        }
        if (($type === ArtifactType::InitiativeRecord && $initiative === null) || ($type === ArtifactType::ProjectRecord && $project === null)) {
            throw new LogicException('O tipo de artefato não corresponde ao seu pai.');
        }

        return $type;
    }

    /** @param array<mixed> $content @param array<mixed>|null $metadata */
    private function appendLocked(Artifact $artifact, array $content, ?array $metadata, int $schemaVersion, string $reason, User $actor, ?Initiative $initiative, ?Project $project): ArtifactRevision
    {
        if ($schemaVersion < 1 || $schemaVersion > 65535) {
            throw new LogicException('A versão de esquema é inválida.');
        }
        $initiativeVersion = $initiative === null ? null : InitiativeConfigurationVersion::query()->where('initiative_id', $initiative->id)->where('organization_id', $artifact->organization_id)->whereNull('superseded_at')->lockForUpdate()->first();
        $projectVersion = $project === null ? null : ProjectConfigurationVersion::query()->where('project_id', $project->id)->where('organization_id', $artifact->organization_id)->whereNull('superseded_at')->lockForUpdate()->first();
        if (($initiative !== null && $initiativeVersion === null) || ($project !== null && $projectVersion === null)) {
            throw new LogicException('A configuração temporal vigente do pai é obrigatória para registrar a revisão.');
        }
        $sequence = $artifact->current_revision_sequence + 1;
        $envelope = [
            'artifact_id' => $artifact->id,
            'sequence' => $sequence,
            'schema_version' => $schemaVersion,
            'content' => $content,
            'metadata' => $metadata,
            'source_initiative_configuration_version_id' => $initiativeVersion?->id,
            'source_project_configuration_version_id' => $projectVersion?->id,
        ];
        $revision = ArtifactRevision::createThroughService([
            'organization_id' => $artifact->organization_id,
            'artifact_id' => $artifact->id,
            'sequence' => $sequence,
            'schema_version' => $schemaVersion,
            'content' => $content,
            'metadata' => $metadata,
            'source_initiative_configuration_version_id' => $initiativeVersion?->id,
            'source_project_configuration_version_id' => $projectVersion?->id,
            'checksum' => $this->canonicalizer->checksum($envelope),
            'changed_by' => $actor->id,
            'change_reason' => $reason,
            'recorded_at' => now(),
        ]);
        $artifact->update(['current_revision_sequence' => $sequence]);

        return $revision;
    }

    private function authorize(User $actor, ?int $expectedOrganizationId = null): int
    {
        if (! $actor->is_active || ! $this->context->active() || ($expectedOrganizationId !== null && $this->context->id() !== $expectedOrganizationId)) {
            throw new LogicException('O contexto organizacional ativo é obrigatório.');
        }
        if ($actor->isSuperAdmin()) {
            if ($this->context->isPlatformAccess()) {
                return (int) $this->context->id();
            }

            throw new LogicException('Superadministradores exigem acesso temporário explícito à organização.');
        }
        $membership = $actor->organizationMemberships()->where('organization_id', $this->context->id())->where('status', OrganizationMembershipStatus::Active->value)->first();
        if ($membership === null || ! $actor->administersCurrentOrganization()) {
            throw new LogicException('O usuário não possui permissão administrativa ativa para artefatos.');
        }

        return (int) $this->context->id();
    }

    /** @return array<mixed> */
    private function arrayValue(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new LogicException("O campo {$field} deve ser um objeto ou lista JSON.");
        }

        return $value;
    }

    private function requiredText(mixed $value, string $field, int $max): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || mb_strlen($value) > $max) {
            throw new LogicException("O campo {$field} é obrigatório e excede o limite permitido.");
        }

        return $value;
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > $max) {
            throw new LogicException('A descrição é inválida.');
        }

        return trim($value);
    }

    /** @param array<string, mixed> $attributes */
    private function rejectUnknownCreateFields(array $attributes): void
    {
        $allowed = ['initiative_id', 'project_id', 'type', 'title', 'description', 'content', 'metadata', 'schema_version', 'change_reason'];
        if (array_diff(array_keys($attributes), $allowed) !== []) {
            throw new LogicException('A criação de artefato recebeu campos não permitidos.');
        }
    }
}
