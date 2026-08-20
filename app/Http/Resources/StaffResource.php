<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Staff */
class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'school_id'       => $this->school_id,
            'staff_number'    => $this->staff_number,
            'first_name'      => $this->first_name,
            'middle_name'     => $this->middle_name,
            'last_name'       => $this->last_name,
            'full_name'       => $this->full_name,
            'gender'          => $this->gender,
            'phone'           => $this->phone,
            'email'           => $this->email,
            'designation'     => $this->designation,
            'department'      => $this->department,
            'employment_date' => $this->employment_date?->toDateString(),
            'status'          => $this->status,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
