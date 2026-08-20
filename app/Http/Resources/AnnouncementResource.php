<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Announcement */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience,

            'class_level' => $this->whenLoaded('classLevel', fn () => $this->classLevel ? [
                'id' => $this->classLevel->id,
                'name' => $this->classLevel->name,
            ] : null),

            'class_arm' => $this->whenLoaded('classArm', fn () => $this->classArm ? [
                'id' => $this->classArm->id,
                'full_name' => $this->classArm->full_name,
            ] : null),

            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_published' => $this->isPublished(),

            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
