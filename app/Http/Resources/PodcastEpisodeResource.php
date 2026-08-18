<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PodcastEpisodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * IMPORTANT:
     *
     * audio_file and video_file are private storage paths.
     * They must NEVER be exposed through the public API.
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'uuid' => $this->uuid,

            'podcast_id' => $this->podcast_id,

            'title' => $this->title,

            'slug' => $this->slug,

            'description' => $this->description,

            'cover_image' => $this->artwork(),

            'episode_number' =>
                (int) $this->episode_number,

            'duration_seconds' =>
                (int) $this->duration_seconds,

            'duration_minutes' =>
                $this->durationInMinutes(),

            'status' => $this->status,

            'is_free' => (bool) $this->is_free,

            'is_featured' =>
                (bool) $this->is_featured,

            'has_audio' =>
                $this->hasAudio(),

            'has_video' =>
                $this->hasVideo(),

            'has_media' =>
                $this->hasMedia(),

            'published_at' =>
                $this->published_at,

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,

            'podcast' =>
                new PodcastResource(
                    $this->whenLoaded('podcast')
                ),
        ];
    }
}