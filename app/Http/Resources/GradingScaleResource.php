<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GradingScale */
class GradingScaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'school_id'       => $this->school_id,
            'class_level_id'  => $this->class_level_id,
            'name'            => $this->name,
            'is_default'      => $this->is_default,

            'bands' => GradingScaleBandResource::collection($this->whenLoaded('bands')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
