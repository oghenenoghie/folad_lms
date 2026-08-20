<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = [
        'amount_due' => 'integer',
        'issued_at' => 'date',
        'due_date' => 'date',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Net of all payment rows -- reversals are negative amounts, so this nets out on its own. */
    public function amountPaid(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    public function balance(): int
    {
        return $this->amount_due - $this->amountPaid();
    }

    public function status(): string
    {
        $paid = $this->amountPaid();

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $this->amount_due ? 'paid' : 'partial';
    }
}
