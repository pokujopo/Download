<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caption' => $this->caption,
            'status' => $this->status->value,
            'scheduled_at' => $this->scheduled_at,
            'published_at' => $this->published_at,

            'media' => $this->whenLoaded('media', function () {
                return $this->media->map(fn ($media) => [
                    'id' => $media->id,
                    'type' => $media->type,
                    'public_url' => $media->public_url,
                ]);
            }),

            'destinations' => $this->whenLoaded('destinations', function () {
                return $this->destinations->map(fn ($destination) => [
                    'id' => $destination->id,
                    'provider' => $destination->provider->value,
                    'status' => $destination->status->value,
                    'scheduled_at' => $destination->scheduled_at,
                    'published_at' => $destination->published_at,
                    'external_media_id' => $destination->external_media_id,
                    'error_code' => $destination->error_code,
                    'error_message' => $destination->error_message,
                ]);
            }),
        ];
    }
}