<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GradingScaleBand */
class GradingScaleBandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'grade'      => $this->grade,
            'min_score'  => $this->min_score,
            'max_score'  => $this->max_score,
            'remark'     => $this->remark,
        ];
    }
}
