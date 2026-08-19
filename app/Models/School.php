<?php

namespace App\Models;

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        // Every school needs its own copy of the school-scoped roles
        // (spatie teams keys each role row by school_id) — seed them here
        // rather than relying on an admin to remember a manual step.
        static::created(fn (School $school) => RoleSeeder::seedForSchool($school->id));
    }

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

    public function currentSession(): ?AcademicSession
    {
        return $this->academicSessions()->where('is_current', true)->first();
    }
}
