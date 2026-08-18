<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Guardian */
class GuardianSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'full_name'         => $this->full_name,
            'phone'             => $this->phone,
            'relationship'      => $this->pivot?->relationship,
            'is_primary_contact' => (bool) $this->pivot?->is_primary_contact,
        ];
    }
}
