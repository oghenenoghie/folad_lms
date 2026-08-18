<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AssessmentComponent */
class AssessmentComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'school_id'  => $this->school_id,
            'term_id'    => $this->term_id,
            'name'       => $this->name,
            'max_score'  => $this->max_score,
            'sequence'   => $this->sequence,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
