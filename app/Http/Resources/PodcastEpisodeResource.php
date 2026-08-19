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
    public function toArray(Request $request): array
    {
        /*
         * Determine the public artwork.
         *
         * Priority:
         * 1. Episode cover image
         * 2. Podcast cover image
         * 3. Default podcast artwork
         */
        $coverImage = $this->cover_image;

        if (
            empty($coverImage) &&
            $this->relationLoaded('podcast') &&
            $this->podcast
        ) {
            $coverImage = $this->podcast->cover_image;
        }

        $coverImage ??= 'podcasts/default.jpg';

        /*
         * Media availability.
         *
         * We check the stored paths directly instead of
         * calling methods that may not exist on the model.
         */
        $hasAudio = ! empty($this->audio_file);
        $hasVideo = ! empty($this->video_file);
        $hasMedia = $hasAudio || $hasVideo;

        return [
            'id' => $this->id,

            'uuid' => $this->uuid,

            'podcast_id' => $this->podcast_id,

            'title' => $this->title,

            'slug' => $this->slug,

            'description' => $this->description,

            /*
             * Public artwork only.
             */
            'cover_image' => $coverImage,

            'episode_number' => (int) $this->episode_number,

            'duration_seconds' => (int) $this->duration_seconds,

            'duration_minutes' => $this->durationInMinutes(),

            'status' => $this->status,

            'is_free' => (bool) $this->is_free,

            'is_featured' => (bool) $this->is_featured,

            'has_audio' => $hasAudio,

            'has_video' => $hasVideo,

            'has_media' => $hasMedia,

            /*
             * Do NOT expose:
             *
             * audio_file
             * video_file
             *
             * Those are private storage paths.
             *
             * Playback must happen through the protected
             * streaming endpoints.
             */

            'published_at' => $this->published_at,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,

            /*
             * Include the podcast only when it has already
             * been eager-loaded.
             */
            'podcast' => $this->whenLoaded(
                'podcast',
                fn () => new PodcastResource($this->podcast)
            ),
        ];
    }
}