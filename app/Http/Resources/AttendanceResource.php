<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Attendance */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'date'          => $this->date?->toDateString(),
            'status'        => $this->status,
            'remarks'       => $this->remarks,

            'student' => $this->whenLoaded('enrollment', fn () => $this->enrollment->relationLoaded('student') ? [
                'id'               => $this->enrollment->student->id,
                'admission_number' => $this->enrollment->student->admission_number,
                'full_name'        => $this->enrollment->student->full_name,
            ] : null),

            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy ? [
                'id'        => $this->recordedBy->id,
                'full_name' => $this->recordedBy->full_name,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
