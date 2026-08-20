<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ClassArm */
class ClassArmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'school_id'       => $this->school_id,
            'class_level_id'  => $this->class_level_id,
            'name'            => $this->name,
            'full_name'       => $this->full_name,
            'capacity'        => $this->capacity,

            'class_level' => $this->whenLoaded('classLevel', fn () => [
                'id'   => $this->classLevel->id,
                'name' => $this->classLevel->name,
                'rank' => $this->classLevel->rank,
            ]),

            'form_teacher' => $this->whenLoaded('formTeacher', fn () => $this->formTeacher ? [
                'id'        => $this->formTeacher->id,
                'full_name' => $this->formTeacher->full_name,
            ] : null),

            // Active headcount for this arm; populated via loadCount/withCount
            // in the controller (null if the caller didn't ask for it).
            'enrolled_count' => $this->active_enrollment_count,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
