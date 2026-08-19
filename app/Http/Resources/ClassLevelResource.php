<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ClassLevel */
class ClassLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'school_id' => $this->school_id,
            'name'      => $this->name,
            'rank'      => $this->rank,

            'arms' => ClassArmResource::collection($this->whenLoaded('arms')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
