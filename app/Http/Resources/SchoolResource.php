<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\School */
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
            'logo_path'  => $this->logo_path,
            'motto'      => $this->motto,
            'currency'   => $this->currency,
            'settings'   => $this->settings,
            'is_active'  => $this->is_active,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
