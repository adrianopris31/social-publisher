<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caption' => $this->caption,
            'publish_at' => $this->publish_at?->toIso8601String(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'video' => [
                'id' => $this->video->id,
                'original_filename' => $this->video->original_filename,
                'duration_ms' => $this->video->duration_ms,
            ],
            'publications' => PublicationResource::collection($this->whenLoaded('publications')),
        ];
    }
}
