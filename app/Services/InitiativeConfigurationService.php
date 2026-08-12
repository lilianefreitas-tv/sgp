<?php

namespace App\Services;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\InitiativeState;
use App\Enums\ManagementLevel;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\ProjectMethodology;
use App\Models\Initiative;
use App\Models\InitiativeConfigurationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class InitiativeConfigurationService
{
    /** @var array<string, class-string> */
    private const DIMENSIONS = [
        'origin' => InitiativeOrigin::class,
        'execution_nature' => ExecutionNature::class,
        'financial_management_mode' => FinancialManagementMode::class,
        'management_level' => ManagementLevel::class,
        'methodology' => ProjectMethodology::class,
    ];

    public function __construct(private InitiativeCodeGenerator $codes, private OrganizationContext $context) {}

    public function create(array $attributes, User $actor, string $justification): Initiative
    {
        if (blank(trim($justification))) {
            throw new LogicException('A justificativa é obrigatória.');
        }

        $attributes = $this->normalizeCreateAttributes($attributes);
        $organizationId = $this->authorizedOrganization($actor);

        return DB::transaction(function () use ($attributes, $actor, $justification, $organizationId): Initiative {
            $now = now();
            $initiative = Initiative::create($attributes + [
                'code' => $this->codes->next($organizationId),
                'created_by' => $actor->id,
                'lock_version' => 0,
            ]);
            $this->version($initiative, 1, $actor, $justification, $now);

            return $initiative;
        });
    }

    public function change(Initiative $initiative, array $dimensions, User $actor, string $justification): InitiativeConfigurationVersion
    {
        if (blank(trim($justification))) {
            throw new LogicException('A justificativa é obrigatória.');
        }

        $dimensions = $this->normalizeDimensions($dimensions);
        $this->authorizedOrganization($actor, (int) $initiative->organization_id);

        return DB::transaction(function () use ($initiative, $dimensions, $actor, $justification): InitiativeConfigurationVersion {
            $locked = Initiative::query()->whereKey($initiative->id)->lockForUpdate()->firstOrFail();
            $current = $locked->configurationVersions()->whereNull('superseded_at')->lockForUpdate()->firstOrFail();
            $now = now();
            $current->supersede($now);
            $locked->update($dimensions + ['lock_version' => $locked->lock_version + 1]);

            return $this->version($locked, $current->sequence + 1, $actor, $justification, $now);
        });
    }

    private function version(Initiative $initiative, int $sequence, User $actor, string $justification, $now): InitiativeConfigurationVersion
    {
        return $initiative->configurationVersions()->create(['sequence' => $sequence, 'origin' => $initiative->origin,
            'execution_nature' => $initiative->execution_nature, 'financial_management_mode' => $initiative->financial_management_mode,
            'management_level' => $initiative->management_level, 'methodology' => $initiative->methodology, 'effective_from' => $now,
            'changed_by' => $actor->id, 'justification' => $justification, 'recorded_at' => $now]);
    }

    /** @return array<string, mixed> */
    private function normalizeCreateAttributes(array $attributes): array
    {
        $allowed = ['title', 'context', 'state', ...array_keys(self::DIMENSIONS)];
        $this->rejectUnknown($attributes, $allowed);
        if (! isset($attributes['title']) || blank(trim((string) $attributes['title'])) || ! isset($attributes['origin'])) {
            throw new LogicException('Título e origem são obrigatórios.');
        }

        $attributes['state'] = $this->normalizeEnum(InitiativeState::class, $attributes['state'] ?? InitiativeState::Draft, 'state');

        foreach (self::DIMENSIONS as $field => $enum) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }
            $attributes[$field] = $this->normalizeEnum($enum, $attributes[$field], $field);
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function normalizeDimensions(array $dimensions, bool $requireAny = true): array
    {
        $this->rejectUnknown($dimensions, array_keys(self::DIMENSIONS));
        if ($requireAny && $dimensions === []) {
            throw new LogicException('Ao menos uma dimensão deve ser informada.');
        }

        foreach ($dimensions as $field => $value) {
            if (! array_key_exists($field, self::DIMENSIONS)) {
                continue;
            }
            $dimensions[$field] = $this->normalizeEnum(self::DIMENSIONS[$field], $value, $field);
        }

        return $dimensions;
    }

    private function normalizeEnum(string $enum, mixed $value, string $field): mixed
    {
        $case = $value instanceof $enum ? $value : (is_string($value) ? $enum::tryFrom($value) : null);
        if ($case === null || $case === ManagementLevel::Simplified) {
            throw new LogicException("Valor inválido para {$field}.");
        }

        return $case;
    }

    /** @param list<string> $allowed */
    private function rejectUnknown(array $attributes, array $allowed): void
    {
        if (array_diff(array_keys($attributes), $allowed) !== []) {
            throw new LogicException('A operação recebeu campos não permitidos.');
        }
    }

    private function authorizedOrganization(User $actor, ?int $expected = null): int
    {
        if (! $actor->exists || ! $actor->is_active) {
            throw new LogicException('O ator deve existir e estar ativo.');
        }
        if (! $this->context->active()) {
            throw new LogicException('A operação exige contexto organizacional ativo.');
        }
        $id = (int) $this->context->id();
        if ($expected !== null && $expected !== $id) {
            throw new LogicException('A iniciativa não pertence ao contexto ativo.');
        }
        if ($actor->isSuperAdmin() && $this->context->isPlatformAccess()) {
            return $id;
        }
        $active = $actor->organizationMemberships()->where('organization_id', $id)
            ->where('status', OrganizationMembershipStatus::Active->value)->exists();
        if (! $active) {
            throw new LogicException('O ator não possui vínculo organizacional ativo.');
        }

        return $id;
    }
}
