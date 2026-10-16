<?php

namespace App\Http\Resources;

use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Publication
 */
class PublicationResource extends JsonResource
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
            'platform' => $this->platform,
            'account' => $this->socialAccount ? [
                'id' => $this->socialAccount->id,
                'display_name' => $this->socialAccount->display_name,
                'avatar_url' => $this->socialAccount->avatar_url,
            ] : null,
            'title' => $this->title,
            'caption' => $this->caption,
            'privacy' => $this->privacy,
            'status' => $this->status,
            'external_url' => $this->external_url,
            'error_message' => $this->error_message,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
