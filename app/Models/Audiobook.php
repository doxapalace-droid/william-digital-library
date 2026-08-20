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
        'is_free',
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
            'is_free' => 'boolean',
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
     *
     * Chapters are automatically ordered by track number.
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(AudiobookChapter::class)
            ->orderBy('track_number');
    }

    /**
     * Entitlements granted for this audiobook.
     *
     * An entitlement determines whether a customer
     * is allowed to access and stream this audiobook.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(AudiobookEntitlement::class);
    }

    /**
     * Determine whether the audiobook is currently active.
     *
     * An audiobook is active only when:
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
     * Determine whether this audiobook is free.
     *
     * Free status is explicitly controlled by is_free.
     * Price alone does not determine whether an audiobook is free.
     */
    public function isFree(): bool
    {
        return $this->is_free === true;
    }

    /**
     * Determine whether the audiobook is available
     * for purchase or free acquisition.
     */
    public function isPurchasable(): bool
    {
        return $this->isActive()
            && (
                $this->isFree()
                || (float) $this->price >= 0
            );
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