<?php

namespace App\Models;

use App\Traits\HasUuid;
use Database\Factories\PodcastEpisodeProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'podcast_episode_id',
    'position_seconds',
    'duration_seconds',
    'progress_percent',
    'is_completed',
    'last_played_at',
])]
class PodcastEpisodeProgress extends Model
{
    /** @use HasFactory<PodcastEpisodeProgressFactory> */
    use HasFactory, HasUuid;

    /**
     * Attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'progress_percent' => 'decimal:2',
            'is_completed' => 'boolean',
            'last_played_at' => 'datetime',
        ];
    }

    /**
     * Use UUID for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The user who owns this progress record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The podcast episode being tracked.
     */
    public function podcastEpisode(): BelongsTo
    {
        return $this->belongsTo(
            PodcastEpisode::class,
            'podcast_episode_id'
        );
    }

    /**
     * Determine whether playback has started.
     */
    public function hasStarted(): bool
    {
        return $this->position_seconds > 0;
    }

    /**
     * Determine whether the episode is completed.
     */
    public function isCompleted(): bool
    {
        return (bool) $this->is_completed;
    }

    /**
     * Calculate playback percentage.
     */
    public function calculatedProgressPercent(): float
    {
        if ($this->duration_seconds <= 0) {
            return 0.0;
        }

        $percentage = (
            $this->position_seconds
            / $this->duration_seconds
        ) * 100;

        return min(
            100.0,
            max(
                0.0,
                round($percentage, 2)
            )
        );
    }

    /**
     * Backwards-compatible method name.
     */
    public function calculatedProgressPercentage(): float
    {
        return $this->calculatedProgressPercent();
    }

    /**
     * Calculate remaining playback time.
     */
    public function remainingSeconds(): int
    {
        return max(
            0,
            $this->duration_seconds
            - $this->position_seconds
        );
    }
}