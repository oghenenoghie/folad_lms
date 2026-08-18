<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingScaleBand extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = [
        'min_score' => 'decimal:2',
        'max_score' => 'decimal:2',
    ];

    public function gradingScale(): BelongsTo { return $this->belongsTo(GradingScale::class); }
}
