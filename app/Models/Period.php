<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = [
        'sequence' => 'integer',
        'is_teaching_period' => 'boolean',
    ];

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }
}
