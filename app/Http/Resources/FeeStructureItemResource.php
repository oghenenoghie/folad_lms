<?php

namespace App\Http\Resources;

use App\Models\FeeStructureItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeeStructureItem */
class FeeStructureItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => $this->amount,
        ];
    }
}
