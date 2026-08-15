<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audiobook extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'book_id',
        'description',
        'cover_image',
        'price',
        'currency',
        'status',
        'duration_seconds',
        'published_at',
    ];

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
     * Use UUID for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The book this audiobook belongs to.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Chapters/tracks belonging to this audiobook.
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(AudiobookChapter::class)
            ->orderBy('track_number');
    }

    /**
     * Determine whether the audiobook is currently active.
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
     * Determine whether the audiobook is available
     * for purchase.
     */
    public function isPurchasable(): bool
    {
        return $this->isActive()
            && (float) $this->price >= 0;
    }

    /**
     * Get the number of chapters/tracks.
     */
    public function chaptersCount(): int
    {
        return $this->chapters()->count();
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