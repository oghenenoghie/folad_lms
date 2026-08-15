<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'date_of_birth'  => 'date',
        'admission_date' => 'date',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
                    ->withPivot(['relationship', 'is_primary_contact'])
                    ->withTimestamps();
    }

    public function enrollments(): HasMany { return $this->hasMany(Enrollment::class); }

    /**
     * Current placement is DERIVED, never stored on the row.
     * Active enrollment for the school's current session.
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)
                    ->where('status', 'active')
                    ->whereHas('academicSession', fn ($q) => $q->where('is_current', true));
    }

    public function currentClassArm(): ?ClassArm
    {
        return $this->currentEnrollment?->classArm;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }
}
