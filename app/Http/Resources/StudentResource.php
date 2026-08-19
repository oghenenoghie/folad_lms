<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'school_id'         => $this->school_id,
            'user_id'           => $this->user_id,
            'admission_number'  => $this->admission_number,
            'first_name'        => $this->first_name,
            'last_name'         => $this->last_name,
            'middle_name'       => $this->middle_name,
            'full_name'         => $this->full_name,
            'gender'            => $this->gender,
            'date_of_birth'     => $this->date_of_birth?->toDateString(),
            'admission_date'    => $this->admission_date?->toDateString(),
            'blood_group'       => $this->blood_group,
            'address'           => $this->address,
            'status'            => $this->status,
            'has_login'         => (bool) $this->user_id,
            'current_class_arm' => $this->whenLoaded(
                'currentEnrollment',
                fn () => $this->currentEnrollment?->classArm ? new ClassArmResource($this->currentEnrollment->classArm) : null,
            ),
            'guardians'         => GuardianResource::collection($this->whenLoaded('guardians')),
        ];
    }
}
