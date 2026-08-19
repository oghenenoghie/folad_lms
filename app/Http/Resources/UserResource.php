<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'avatar_url'  => $this->avatar_path ? \Storage::url($this->avatar_path) : null,
            'is_active'   => $this->is_active,
            'school_id'   => $this->school_id,
            'is_super_admin' => $this->isSuperAdmin(),
            'roles'       => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'staff_id'    => $this->staff?->id,
            'student_id'  => $this->student?->id,
            'guardian_id' => $this->guardian?->id,
        ];
    }
}
