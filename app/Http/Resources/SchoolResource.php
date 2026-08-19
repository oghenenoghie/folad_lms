<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'code'       => $this->code,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'address'    => $this->address,
            'logo_url'   => $this->logo_path ? \Storage::url($this->logo_path) : null,
            'motto'      => $this->motto,
            'currency'   => $this->currency,
            'settings'   => $this->settings,
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
