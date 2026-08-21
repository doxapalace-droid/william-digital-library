<?php

namespace App\Models;

use App\Traits\HasUuid;
use Database\Factories\BundleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleItem extends Model
{
    /** @use HasFactory<BundleItemFactory> */
    use HasFactory;
    use HasUuid;

    /**
     * Supported product types.
     */
    public const TYPE_BOOK = 'book';

    public const TYPE_AUDIOBOOK = 'audiobook';

    public const TYPE_COURSE = 'course';

    public const TYPE_VIDEO = 'video';

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'bundle_id',
        'item_type',
        'book_id',
        'audiobook_id',
        'course_id',
        'video_id',
    ];

    /**
     * Use UUID for public route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Bundle containing this item.
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    /**
     * Book contained in this bundle item.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Audiobook contained in this bundle item.
     */
    public function audiobook(): BelongsTo
    {
        return $this->belongsTo(Audiobook::class);
    }

    /**
     * Course contained in this bundle item.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Video contained in this bundle item.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Determine whether this item is a book.
     */
    public function isBook(): bool
    {
        return $this->item_type === self::TYPE_BOOK;
    }

    /**
     * Determine whether this item is an audiobook.
     */
    public function isAudiobook(): bool
    {
        return $this->item_type === self::TYPE_AUDIOBOOK;
    }

    /**
     * Determine whether this item is a course.
     */
    public function isCourse(): bool
    {
        return $this->item_type === self::TYPE_COURSE;
    }

    /**
     * Determine whether this item is a video.
     */
    public function isVideo(): bool
    {
        return $this->item_type === self::TYPE_VIDEO;
    }

    /**
     * Get the actual product represented by this
     * bundle item.
     */
    public function product(): ?Model
    {
        return match ($this->item_type) {
            self::TYPE_BOOK => $this->book,
            self::TYPE_AUDIOBOOK => $this->audiobook,
            self::TYPE_COURSE => $this->course,
            self::TYPE_VIDEO => $this->video,
            default => null,
        };
    }
}