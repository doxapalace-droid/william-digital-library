<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Podcast extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_image',
        'status',
        'is_featured',
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
     * Episodes belonging to this podcast.
     *
     * Episodes are always returned in episode-number order.
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(PodcastEpisode::class)
            ->orderBy('episode_number');
    }

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the podcast is currently active.
     *
     * A podcast is active only when:
     *
     * 1. Its status is active.
     * 2. It is not scheduled for future publication.
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
     * Determine whether the podcast is publicly available.
     */
    public function isPubliclyAvailable(): bool
    {
        return $this->isActive();
    }

    /**
     * Get the number of episodes.
     */
    public function episodesCount(): int
    {
        return $this->episodes()->count();
    }

    /**
     * Get the number of currently published episodes.
     */
    public function publishedEpisodesCount(): int
    {
        return $this->episodes()
            ->where('status', 'active')
            ->where(function ($query) {
                $query
                    ->whereNull('published_at')
                    ->orWhere(
                        'published_at',
                        '<=',
                        now()
                    );
            })
            ->count();
    }
}