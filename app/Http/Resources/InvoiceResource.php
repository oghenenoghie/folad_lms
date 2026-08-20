<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_due' => $this->amount_due,
            'issued_at' => $this->issued_at?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),

            // Derived from the payment rows, never stored -- a reversal is
            // just a negative payment, so summing nets it out naturally.
            'amount_paid' => $this->whenLoaded('payments', fn () => $this->amountPaid()),
            'balance' => $this->whenLoaded('payments', fn () => $this->balance()),
            'status' => $this->whenLoaded('payments', fn () => $this->status()),

            'student' => $this->whenLoaded('enrollment', fn () => $this->enrollment->relationLoaded('student') ? [
                'id' => $this->enrollment->student->id,
                'admission_number' => $this->enrollment->student->admission_number,
                'full_name' => $this->enrollment->student->full_name,
            ] : null),

            'term' => $this->whenLoaded('term', fn () => [
                'id' => $this->term->id,
                'name' => $this->term->name,
            ]),

            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
