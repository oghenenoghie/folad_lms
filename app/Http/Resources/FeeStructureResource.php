<?php

namespace App\Http\Resources;

use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeeStructure */
class FeeStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            'class_level' => $this->whenLoaded('classLevel', fn () => [
                'id' => $this->classLevel->id,
                'name' => $this->classLevel->name,
            ]),

            'term' => $this->whenLoaded('term', fn () => [
                'id' => $this->term->id,
                'name' => $this->term->name,
            ]),

            'is_published' => $this->isPublished(),
            'published_at' => $this->published_at?->toIso8601String(),
            'total_amount' => $this->whenLoaded('items', fn () => $this->items->sum('amount')),

            'items' => FeeStructureItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
