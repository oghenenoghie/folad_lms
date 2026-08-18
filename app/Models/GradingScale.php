<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScale extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean'];

    public function classLevel(): BelongsTo { return $this->belongsTo(ClassLevel::class); }
    public function bands(): HasMany        { return $this->hasMany(GradingScaleBand::class); }

    /** Looks up which band a score falls into, ordered so the highest band wins ties. */
    public function bandFor(float $score): ?GradingScaleBand
    {
        return $this->bands()
            ->where('min_score', '<=', $score)
            ->where('max_score', '>=', $score)
            ->orderByDesc('min_score')
            ->first();
    }
}
