<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'school_id' => $this->school_id,
            'name'      => $this->name,
            'rank'      => $this->rank,
            'arms'      => ClassArmResource::collection($this->whenLoaded('arms')),
            'subjects'  => SubjectResource::collection($this->whenLoaded('subjects')),
        ];
    }
}
