<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'amount' => $this->amount,
            'method' => $this->method,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->toDateString(),
            'notes' => $this->notes,
            'is_reversal' => $this->isReversal(),
            'reversal_of_payment_id' => $this->reversal_of_payment_id,

            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy ? [
                'id' => $this->recordedBy->id,
                'full_name' => $this->recordedBy->full_name,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
