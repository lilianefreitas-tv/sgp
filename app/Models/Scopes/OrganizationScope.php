<?php

namespace App\Models\Scopes;

use App\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    public function __construct(private readonly OrganizationContext $context)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        if (! $this->context->active()) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('organization_id'),
            $this->context->id(),
        );
    }
}
