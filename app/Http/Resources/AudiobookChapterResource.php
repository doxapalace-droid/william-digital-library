<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AudiobookChapterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Private audio storage information is deliberately
     * excluded from the public API.
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'title' => $this->title,

            'description' => $this->description,

            'track_number' => $this->track_number,

            'duration_seconds' => $this->duration_seconds,

            'duration_minutes' => $this->durationInMinutes(),

            'is_preview' => $this->isPreviewAvailable(),

            'published_at' => $this->published_at,

            'status' => $this->status,

            /*
             * The frontend uses this endpoint to request
             * playback. The actual audio file remains private.
             */
            'stream_url' => route(
                'audiobook-chapters.stream',
                [
                    'chapter' => $this->uuid,
                ]
            ),
        ];
    }
}