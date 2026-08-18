<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodcastEpisode extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'podcast_id',
        'title',
        'slug',
        'description',
        'cover_image',
        'audio_file',
        'video_file',
        'duration_seconds',
        'status',
        'is_free',
        'is_featured',
        'episode_number',
        'published_at',
    ];

    /**
     * Use UUID for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Podcast this episode belongs to.
     */
    public function podcast(): BelongsTo
    {
        return $this->belongsTo(Podcast::class);
    }

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'is_free' => 'boolean',
            'is_featured' => 'boolean',
            'episode_number' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the episode itself is active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (
            $this->published_at !== null
            && $this->published_at->isFuture()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether both the episode and its
     * parent podcast are publicly available.
     */
    public function isPubliclyAvailable(): bool
    {
        return $this->isActive()
            && $this->podcast !== null
            && $this->podcast->isPubliclyAvailable();
    }

    /**
     * Determine whether the episode has audio.
     */
    public function hasAudio(): bool
    {
        return ! empty($this->audio_file);
    }

    /**
     * Determine whether the episode has video.
     */
    public function hasVideo(): bool
    {
        return ! empty($this->video_file);
    }

    /**
     * Determine whether the episode has either
     * audio or video content.
     */
    public function hasMedia(): bool
    {
        return $this->hasAudio()
            || $this->hasVideo();
    }

    /**
     * Get duration in minutes.
     */
    public function durationInMinutes(): float
    {
        return round(
            ((int) $this->duration_seconds) / 60,
            2
        );
    }

    /**
     * Get the episode artwork.
     *
     * Episode artwork takes priority over the
     * parent podcast artwork.
     */
    public function artwork(): ?string
    {
        return $this->cover_image
            ?? $this->podcast?->cover_image;
    }
}