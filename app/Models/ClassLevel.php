<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassLevel extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['rank' => 'integer'];

    public function arms(): HasMany { return $this->hasMany(ClassArm::class); }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject')->withTimestamps();
    }
}
