<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'settings'  => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany            { return $this->hasMany(User::class); }
    public function academicSessions(): HasMany { return $this->hasMany(AcademicSession::class); }
    public function terms(): HasMany            { return $this->hasMany(Term::class); }
    public function staff(): HasMany            { return $this->hasMany(Staff::class); }
    public function students(): HasMany         { return $this->hasMany(Student::class); }
    public function guardians(): HasMany        { return $this->hasMany(Guardian::class); }
    public function classLevels(): HasMany      { return $this->hasMany(ClassLevel::class); }
    public function classArms(): HasMany        { return $this->hasMany(ClassArm::class); }
    public function subjects(): HasMany         { return $this->hasMany(Subject::class); }
    public function enrollments(): HasMany      { return $this->hasMany(Enrollment::class); }
    public function gradingScales(): HasMany    { return $this->hasMany(GradingScale::class); }
    public function assessmentComponents(): HasMany { return $this->hasMany(AssessmentComponent::class); }
    public function results(): HasMany          { return $this->hasMany(Result::class); }
    public function attendances(): HasMany      { return $this->hasMany(Attendance::class); }

    public function currentSession(): ?AcademicSession
    {
        return $this->academicSessions()->where('is_current', true)->first();
    }
}
