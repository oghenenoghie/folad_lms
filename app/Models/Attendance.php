<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $guarded = [];

    public function enrollment(): BelongsTo { return $this->belongsTo(Enrollment::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(Staff::class, 'recorded_by'); }

    // A plain accessor/mutator instead of a 'date' cast: Eloquent's built-in
    // date cast always writes 'Y-m-d H:i:s' (fromDateTime() ignores the cast
    // format for storage), which a real DATE column truncates but SQLite
    // stores verbatim -- breaking the enrollment_id+date lookup/uniqueness
    // that bulk-mark's updateOrCreate relies on. Forcing 'Y-m-d' on write
    // keeps storage consistent across engines.
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }
}
