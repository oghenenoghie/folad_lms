<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'school_id'  => $this->school_id,
            'user_id'    => $this->user_id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'full_name'  => $this->full_name,
            'phone'      => $this->phone,
            'email'      => $this->email,
            'occupation' => $this->occupation,
            'address'    => $this->address,
            'has_login'  => (bool) $this->user_id,
            'students'   => $this->whenLoaded('students', fn () => $this->students->map(fn ($student) => [
                ...((new StudentResource($student))->resolve()),
                'relationship'       => $student->pivot->relationship,
                'is_primary_contact' => (bool) $student->pivot->is_primary_contact,
            ])),
        ];
    }
}
