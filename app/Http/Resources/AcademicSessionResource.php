<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AcademicSession */
class AcademicSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'school_id'  => $this->school_id,
            'name'       => $this->name,
            'start_date' => $this->start_date?->toDateString(),
            'end_date'   => $this->end_date?->toDateString(),
            'is_current' => $this->is_current,

            'terms' => TermResource::collection($this->whenLoaded('terms')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
