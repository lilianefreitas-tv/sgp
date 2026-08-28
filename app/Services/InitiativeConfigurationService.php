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
use Illuminate\Support\Arr;
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

    public function __construct(
        private InitiativeCodeGenerator $codes,
        private OrganizationContext $context,
        private OrganizationAuditService $audit,
    ) {}

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
        $this->authorizeLifecycle($initiative, $actor);

        return DB::transaction(function () use ($initiative, $dimensions, $actor, $justification): InitiativeConfigurationVersion {
            $locked = Initiative::query()->whereKey($initiative->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($locked);
            if (array_key_exists('origin', $dimensions)
                && $dimensions['origin'] !== $locked->origin
                && $locked->opportunity()->exists()) {
                throw new LogicException('A origem não pode ser alterada depois que a jornada comercial foi iniciada.');
            }
            $current = $locked->configurationVersions()->whereNull('superseded_at')->lockForUpdate()->firstOrFail();
            $now = now();
            $current->supersede($now);
            $locked->update($dimensions + ['lock_version' => $locked->lock_version + 1]);

            return $this->version($locked, $current->sequence + 1, $actor, $justification, $now);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Initiative $initiative,
        array $attributes,
        User $actor,
        string $justification,
        int $expectedLockVersion,
    ): Initiative {
        $justification = trim($justification);
        if ($justification === '') {
            throw new LogicException('A justificativa da alteração é obrigatória.');
        }

        $allowed = ['title', 'context', ...array_keys(self::DIMENSIONS)];
        $this->rejectUnknown($attributes, $allowed);
        if (blank(trim((string) ($attributes['title'] ?? ''))) || ! isset($attributes['origin'])) {
            throw new LogicException('Título e origem são obrigatórios.');
        }
        $dimensions = $this->normalizeDimensions(Arr::only($attributes, array_keys(self::DIMENSIONS)), false);
        $attributes = Arr::only($attributes, ['title', 'context']) + $dimensions;
        $this->authorizedOrganization($actor, (int) $initiative->organization_id);
        $this->authorizeLifecycle($initiative, $actor);

        return DB::transaction(function () use ($initiative, $attributes, $actor, $justification, $expectedLockVersion): Initiative {
            $locked = Initiative::query()->whereKey($initiative->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($locked);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new LogicException('A iniciativa foi alterada por outra pessoa. Recarregue a página antes de continuar.');
            }

            $old = $locked->only(['title', 'context', ...array_keys(self::DIMENSIONS)]);
            $newOrigin = $attributes['origin'];
            if ($newOrigin !== $locked->origin && $locked->opportunity()->exists()) {
                throw new LogicException('A origem não pode ser alterada depois que a jornada comercial foi iniciada.');
            }

            $changed = collect($attributes)->filter(function (mixed $value, string $field) use ($locked): bool {
                $current = $locked->{$field};
                $current = $current instanceof \BackedEnum ? $current->value : $current;
                $value = $value instanceof \BackedEnum ? $value->value : $value;

                return (string) ($current ?? '') !== (string) ($value ?? '');
            })->keys()->values()->all();
            if ($changed === []) {
                throw new LogicException('Nenhuma alteração foi identificada.');
            }

            $dimensionChanged = array_intersect($changed, array_keys(self::DIMENSIONS)) !== [];
            $now = now();
            if ($dimensionChanged) {
                $current = $locked->configurationVersions()->whereNull('superseded_at')->lockForUpdate()->firstOrFail();
                $current->supersede($now);
                $locked->update($attributes + ['lock_version' => $locked->lock_version + 1]);
                $this->version($locked, $current->sequence + 1, $actor, $justification, $now);
            } else {
                $locked->update($attributes + ['lock_version' => $locked->lock_version + 1]);
            }

            $this->audit->record('initiative.updated', 'success', Initiative::class, $locked->id, [
                'code' => $locked->code,
                'changed_fields' => $changed,
                'before' => $this->scalarValues(Arr::only($old, $changed)),
                'after' => $this->scalarValues(Arr::only($attributes, $changed)),
                'justification' => $justification,
                'configuration_version_created' => $dimensionChanged,
            ], (int) $locked->organization_id, $actor);

            return $locked->fresh(['configurationVersions', 'contracts', 'project']);
        });
    }

    public function cancel(Initiative $initiative, User $actor, string $justification, int $expectedLockVersion): Initiative
    {
        return $this->changeState($initiative, $actor, $justification, $expectedLockVersion, InitiativeState::Cancelled, 'initiative.cancelled');
    }

    public function archive(Initiative $initiative, User $actor, string $justification, int $expectedLockVersion): Initiative
    {
        return $this->changeState($initiative, $actor, $justification, $expectedLockVersion, InitiativeState::Archived, 'initiative.archived');
    }

    public function restore(Initiative $initiative, User $actor, string $justification, int $expectedLockVersion): Initiative
    {
        $this->authorizedOrganization($actor, (int) $initiative->organization_id);
        $this->authorizeLifecycle($initiative, $actor);
        $justification = trim($justification);
        if ($justification === '') {
            throw new LogicException('A justificativa da restauração é obrigatória.');
        }

        return DB::transaction(function () use ($initiative, $actor, $justification, $expectedLockVersion): Initiative {
            $locked = Initiative::query()->whereKey($initiative->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== InitiativeState::Archived || (int) $locked->lock_version !== $expectedLockVersion) {
                throw new LogicException('Somente uma iniciativa arquivada e não alterada pode ser restaurada.');
            }
            $locked->update(['state' => InitiativeState::Draft, 'archived_at' => null, 'lock_version' => $locked->lock_version + 1]);
            $this->auditState($locked, $actor, 'initiative.restored', InitiativeState::Archived, InitiativeState::Draft, $justification);

            return $locked->fresh();
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

    private function changeState(
        Initiative $initiative,
        User $actor,
        string $justification,
        int $expectedLockVersion,
        InitiativeState $target,
        string $action,
    ): Initiative {
        $this->authorizedOrganization($actor, (int) $initiative->organization_id);
        $this->authorizeLifecycle($initiative, $actor);
        $justification = trim($justification);
        if ($justification === '') {
            throw new LogicException('A justificativa da mudança de estado é obrigatória.');
        }

        return DB::transaction(function () use ($initiative, $actor, $justification, $expectedLockVersion, $target, $action): Initiative {
            $locked = Initiative::query()->whereKey($initiative->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($locked);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new LogicException('A iniciativa foi alterada por outra pessoa. Recarregue a página antes de continuar.');
            }
            $from = $locked->state;
            $locked->update([
                'state' => $target,
                'archived_at' => $target === InitiativeState::Archived ? now() : null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->auditState($locked, $actor, $action, $from, $target, $justification);

            return $locked->fresh();
        });
    }

    private function authorizeLifecycle(Initiative $initiative, User $actor): void
    {
        if ((int) $initiative->created_by !== (int) $actor->id && ! $actor->canCreateProjects()) {
            throw new LogicException('Somente o autor da iniciativa ou a gestão autorizada pode alterar seu ciclo de vida.');
        }
    }

    private function assertEditable(Initiative $initiative): void
    {
        if ($initiative->project()->exists() || in_array($initiative->state, [
            InitiativeState::Converted,
            InitiativeState::Cancelled,
            InitiativeState::Archived,
        ], true)) {
            throw new LogicException('A iniciativa não permite alterações estruturais no estado atual.');
        }
    }

    private function auditState(Initiative $initiative, User $actor, string $action, InitiativeState $from, InitiativeState $to, string $justification): void
    {
        $this->audit->record($action, 'success', Initiative::class, $initiative->id, [
            'code' => $initiative->code,
            'from' => $from->value,
            'to' => $to->value,
            'justification' => $justification,
        ], (int) $initiative->organization_id, $actor);
    }

    /** @param array<string, mixed> $values
     *  @return array<string, mixed>
     */
    private function scalarValues(array $values): array
    {
        return collect($values)->map(fn (mixed $value) => $value instanceof \BackedEnum ? $value->value : $value)->all();
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
