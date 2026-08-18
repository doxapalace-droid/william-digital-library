<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PodcastResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Private media paths are never exposed.
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

            'status' => $this->status,

            'is_featured' => (bool) $this->is_featured,

            'published_at' =>
                $this->published_at,

            'episodes_count' =>
                $this->when(
                    isset($this->episodes_count),
                    fn () => (int) $this->episodes_count
                ),

            'published_episodes_count' =>
                $this->when(
                    isset($this->published_episodes_count),
                    fn () => (int) $this->published_episodes_count
                ),

            'episodes' =>
                PodcastEpisodeResource::collection(
                    $this->whenLoaded('episodes')
                ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}