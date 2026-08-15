<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSession extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_current' => 'boolean',
    ];

    public function terms(): HasMany       { return $this->hasMany(Term::class); }
    public function enrollments(): HasMany { return $this->hasMany(Enrollment::class); }

    public function currentTerm(): ?Term
    {
        return $this->terms()->where('is_current', true)->first();
    }
}
