<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'school_id'       => $this->school_id,
            'user_id'         => $this->user_id,
            'staff_number'    => $this->staff_number,
            'first_name'      => $this->first_name,
            'last_name'       => $this->last_name,
            'middle_name'     => $this->middle_name,
            'full_name'       => $this->full_name,
            'gender'          => $this->gender,
            'phone'           => $this->phone,
            'email'           => $this->email,
            'designation'     => $this->designation,
            'department'      => $this->department,
            'employment_date' => $this->employment_date?->toDateString(),
            'status'          => $this->status,
            'has_login'       => (bool) $this->user_id,
        ];
    }
}
