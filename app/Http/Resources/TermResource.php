<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'school_id'           => $this->school_id,
            'academic_session_id' => $this->academic_session_id,
            'name'                => $this->name,
            'sequence'            => $this->sequence,
            'start_date'          => $this->start_date?->toDateString(),
            'end_date'            => $this->end_date?->toDateString(),
            'is_current'          => $this->is_current,
        ];
    }
}
