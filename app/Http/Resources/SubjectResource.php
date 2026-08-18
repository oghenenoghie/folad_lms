<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subject */
class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'school_id'  => $this->school_id,
            'name'       => $this->name,
            'code'       => $this->code,
            'is_core'    => $this->is_core,

            'class_levels' => $this->whenLoaded('classLevels', fn () => $this->classLevels->map(fn ($level) => [
                'id'   => $level->id,
                'name' => $level->name,
            ])),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
