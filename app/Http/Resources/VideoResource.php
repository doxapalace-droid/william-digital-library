<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Private video storage paths are intentionally
     * excluded from the public API response.
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'uuid' => $this->uuid,

            'title' => $this->title,

            'slug' => $this->slug,

            'description' => $this->description,

            'cover_image' => $this->cover_image,

            'price' => number_format(
                (float) $this->price,
                2,
                '.',
                ''
            ),

            'currency' => $this->currency,

            'status' => $this->status,

            'duration_seconds' => $this->duration_seconds,

            'duration_minutes' =>
                $this->durationInMinutes(),

            'published_at' => $this->published_at,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}