<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponent extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = [
        'max_score' => 'decimal:2',
        'sequence'  => 'integer',
    ];

    public function term(): BelongsTo     { return $this->belongsTo(Term::class); }
    public function results(): HasMany    { return $this->hasMany(Result::class); }
}
