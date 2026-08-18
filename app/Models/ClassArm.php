<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassArm extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['capacity' => 'integer'];

    public function classLevel(): BelongsTo { return $this->belongsTo(ClassLevel::class); }
    public function formTeacher(): BelongsTo { return $this->belongsTo(Staff::class, 'form_teacher_id'); }
    public function enrollments(): HasMany   { return $this->hasMany(Enrollment::class); }

    /** "JSS 1A" — level.name + arm.name, no separator (per skill spec). */
    public function getFullNameAttribute(): string
    {
        return trim(($this->classLevel?->name ?? '') . $this->name);
    }
}
