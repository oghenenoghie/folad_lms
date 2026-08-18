<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $table = 'staff';   // Laravel would mis-pluralize otherwise

    protected $guarded = [];

    protected $casts = [
        'employment_date' => 'date',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Classes where this staff member is the form teacher. */
    public function formClasses(): HasMany
    {
        return $this->hasMany(ClassArm::class, 'form_teacher_id');
    }

    public function getFullNameAttribute(): string
    {
        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]));
    }
}
