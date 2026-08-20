<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply to every tenant-scoped model. It:
 *   - filters all reads to the current school (via SchoolScope), and
 *   - auto-fills school_id on create from the current tenant.
 *
 * To query across tenants (reports, super_admin), use:
 *   Model::withoutGlobalScope(SchoolScope::class)->...   OR   Tenancy::disable().
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function ($model) {
            if (empty($model->school_id) && ($schoolId = Tenancy::schoolId())) {
                $model->school_id = $schoolId;
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
