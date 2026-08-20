<?php

namespace App\Http\Resources;

use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimetableEntry */
class TimetableEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,

            'class_arm' => $this->whenLoaded('classArm', fn () => [
                'id' => $this->classArm->id,
                'full_name' => $this->classArm->full_name,
            ]),

            'term' => $this->whenLoaded('term', fn () => [
                'id' => $this->term->id,
                'name' => $this->term->name,
            ]),

            'period' => $this->whenLoaded('period', fn () => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'start_time' => $this->period->start_time,
                'end_time' => $this->period->end_time,
            ]),

            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
            ]),

            'staff' => $this->whenLoaded('staff', fn () => $this->staff ? [
                'id' => $this->staff->id,
                'full_name' => $this->staff->full_name,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
