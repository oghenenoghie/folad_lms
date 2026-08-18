<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Student */
class StudentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'admission_number'   => $this->admission_number,
            'full_name'          => $this->full_name,
            'status'             => $this->status,
            'relationship'       => $this->pivot?->relationship,
            'is_primary_contact' => (bool) $this->pivot?->is_primary_contact,
        ];
    }
}
