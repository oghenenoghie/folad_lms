<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lessThanOrEqualTo(now());
    }

    /**
     * The single source of truth for "can this user see this announcement",
     * shared by the index listing and AnnouncementPolicy::view() so the two
     * can never drift apart. Admin-tier staff see everything, including
     * drafts; everyone else sees only published, audience-matched rows,
     * further narrowed to their own class when one is set.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['school_admin', 'principal', 'head_teacher'])) {
            return $query;
        }

        $query->whereNotNull('published_at')->where('published_at', '<=', now());

        if ($user->hasRole('student')) {
            $classArm = $user->student?->currentClassArm();

            return $query->whereIn('audience', ['all', 'students'])
                ->where(fn (Builder $q) => $q
                    ->where(fn (Builder $q2) => $q2->whereNull('class_level_id')->whereNull('class_arm_id'))
                    ->orWhere('class_arm_id', $classArm?->id)
                    ->orWhere(fn (Builder $q2) => $q2->where('class_level_id', $classArm?->class_level_id)->whereNull('class_arm_id')));
        }

        if ($user->hasRole('guardian')) {
            $classArms = $user->guardian?->students->map(fn ($s) => $s->currentClassArm())->filter() ?? collect();
            $classArmIds = $classArms->pluck('id');
            $classLevelIds = $classArms->pluck('class_level_id');

            return $query->whereIn('audience', ['all', 'guardians'])
                ->where(fn (Builder $q) => $q
                    ->where(fn (Builder $q2) => $q2->whereNull('class_level_id')->whereNull('class_arm_id'))
                    ->orWhereIn('class_arm_id', $classArmIds)
                    ->orWhere(fn (Builder $q2) => $q2->whereIn('class_level_id', $classLevelIds)->whereNull('class_arm_id')));
        }

        // Any other authenticated staff role (teacher, accountant, bursar, ...).
        return $query->whereIn('audience', ['all', 'staff']);
    }
}
