<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'recorded_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'reversal_of_payment_id');
    }

    public function isReversal(): bool
    {
        return $this->reversal_of_payment_id !== null;
    }
}
