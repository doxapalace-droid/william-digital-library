<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PodcastEpisodeProgressResource extends JsonResource
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

            'uuid' => $this->uuid,

            'user_id' => $this->user_id,

            'podcast_episode_id' => $this->podcast_episode_id,

            'position_seconds' => (int) $this->position_seconds,

            'duration_seconds' => (int) $this->duration_seconds,

            /*
             * Public API name.
             *
             * Database column:
             * progress_percent
             */
            'progress_percentage' => (float) $this->progress_percent,

            /*
             * Public API name.
             *
             * Database column:
             * is_completed
             */
            'completed' => (bool) $this->is_completed,

            /*
             * Explicit completion field.
             */
            'is_completed' => (bool) $this->is_completed,

            /*
             * Whether playback has started.
             */
            'has_started' => $this->hasStarted(),

            /*
             * Calculated from position and duration.
             */
            'calculated_progress_percentage' =>
                $this->calculatedProgressPercent(),

            /*
             * Remaining playback time.
             */
            'remaining_seconds' =>
                $this->remainingSeconds(),

            'last_played_at' => $this->last_played_at,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}