<?php

namespace App\Http\Resources;

use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Period */
class PeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'sequence' => $this->sequence,
            'is_teaching_period' => $this->is_teaching_period,
        ];
    }
}
