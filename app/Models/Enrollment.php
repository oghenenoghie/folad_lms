<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    public function student(): BelongsTo         { return $this->belongsTo(Student::class); }
    public function classArm(): BelongsTo        { return $this->belongsTo(ClassArm::class); }
    public function academicSession(): BelongsTo { return $this->belongsTo(AcademicSession::class); }
}
