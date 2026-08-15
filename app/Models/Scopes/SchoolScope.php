<?php

namespace App\Models\Scopes;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $schoolId = Tenancy::schoolId();

        if ($schoolId !== null) {
            $builder->where($model->getTable() . '.school_id', $schoolId);
        }
        // null => no constraint (super_admin / system context)
    }
}
