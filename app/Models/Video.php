<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
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
        'video_file',
        'price',
        'currency',
        'status',
        'duration_seconds',
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
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_seconds' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the video is currently active.
     *
     * A video is active only when:
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
     * Determine whether the video is available
     * for purchase.
     */
    public function isPurchasable(): bool
    {
        return $this->isActive()
            && (float) $this->price >= 0;
    }

    /**
     * Get the duration in minutes.
     */
    public function durationInMinutes(): float
    {
        return round(
            ((int) $this->duration_seconds) / 60,
            2
        );
    }
}