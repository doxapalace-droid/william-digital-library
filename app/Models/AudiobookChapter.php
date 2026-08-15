<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudiobookChapter extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'audiobook_id',
        'title',
        'description',
        'track_number',
        'audio_file',
        'duration_seconds',
        'status',
        'is_preview',
        'published_at',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'track_number' => 'integer',
            'duration_seconds' => 'integer',
            'is_preview' => 'boolean',
            'published_at' => 'datetime',
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
     * Audiobook this chapter belongs to.
     */
    public function audiobook(): BelongsTo
    {
        return $this->belongsTo(Audiobook::class);
    }

    /**
     * Determine whether this chapter is active.
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
     * Determine whether this chapter is available
     * as a free preview.
     */
    public function isPreviewAvailable(): bool
    {
        return $this->isActive() && $this->is_preview;
    }

    /**
     * Get the chapter duration in minutes.
     */
    public function durationInMinutes(): float
    {
        return round(
            ((int) $this->duration_seconds) / 60,
            2
        );
    }
}