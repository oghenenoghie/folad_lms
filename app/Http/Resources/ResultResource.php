<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Result */
class ResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'enrollment_id'           => $this->enrollment_id,
            'subject_id'              => $this->subject_id,
            'assessment_component_id' => $this->assessment_component_id,
            'score'                   => $this->score,

            'student' => $this->whenLoaded('enrollment', fn () => $this->enrollment->relationLoaded('student') ? [
                'id'               => $this->enrollment->student->id,
                'admission_number' => $this->enrollment->student->admission_number,
                'full_name'        => $this->enrollment->student->full_name,
            ] : null),

            'subject' => $this->whenLoaded('subject', fn () => [
                'id'   => $this->subject->id,
                'name' => $this->subject->name,
            ]),

            'assessment_component' => $this->whenLoaded('assessmentComponent', fn () => [
                'id'        => $this->assessmentComponent->id,
                'name'      => $this->assessmentComponent->name,
                'max_score' => $this->assessmentComponent->max_score,
            ]),

            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy ? [
                'id'        => $this->recordedBy->id,
                'full_name' => $this->recordedBy->full_name,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
