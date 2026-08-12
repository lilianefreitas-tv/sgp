<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use LogicException;

trait ImmutableConfigurationVersion
{
    private bool $supersessionInProgress = false;

    protected static function bootImmutableConfigurationVersion(): void
    {
        static::updating(function ($model): void {
            $changed = array_diff(array_keys($model->getDirty()), ['updated_at']);
            if ($changed === []) {
                return;
            }

            if ($changed === ['superseded_at'] && $model->supersessionInProgress) {
                $supersededAt = $model->getAttribute('superseded_at');
                if ($model->getOriginal('superseded_at') === null
                    && $supersededAt !== null
                    && $supersededAt->greaterThanOrEqualTo($model->effective_from)) {
                    return;
                }
            }

            throw new LogicException('Historical versions cannot be changed.');
        });

        static::deleting(fn () => throw new LogicException('Historical versions cannot be deleted.'));
    }

    /** @internal Used only by the configuration services to close the current version. */
    public function supersede(DateTimeInterface $at): void
    {
        if ($this->superseded_at !== null || $at < $this->effective_from) {
            throw new LogicException('Invalid historical version closure.');
        }

        $this->supersessionInProgress = true;
        try {
            $this->update(['superseded_at' => Carbon::instance($at)]);
        } finally {
            $this->supersessionInProgress = false;
        }
    }

    public function forceDelete(): bool
    {
        throw new LogicException('Historical versions cannot be deleted.');
    }
}
