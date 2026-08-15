<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['is_core' => 'boolean'];

    public function classLevels(): BelongsToMany
    {
        return $this->belongsToMany(ClassLevel::class, 'class_subject')->withTimestamps();
    }
}
