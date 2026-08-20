<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $guarded = [];

    protected $casts = ['score' => 'decimal:2'];

    public function enrollment(): BelongsTo         { return $this->belongsTo(Enrollment::class); }
    public function subject(): BelongsTo            { return $this->belongsTo(Subject::class); }
    public function assessmentComponent(): BelongsTo { return $this->belongsTo(AssessmentComponent::class); }
    public function recordedBy(): BelongsTo         { return $this->belongsTo(Staff::class, 'recorded_by'); }
}
