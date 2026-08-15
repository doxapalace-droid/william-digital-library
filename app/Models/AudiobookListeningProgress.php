<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudiobookListeningProgress extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'audiobook_id',
        'audiobook_chapter_id',
        'position_seconds',
        'listened_seconds',
        'progress_percent',
        'is_completed',
        'last_listened_at',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'position_seconds'  => 'integer',
            'listened_seconds'  => 'integer',
            'progress_percent' => 'decimal:2',
            'is_completed'     => 'boolean',
            'last_listened_at' => 'datetime',
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
     * The customer listening to the audiobook.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The audiobook being listened to.
     */
    public function audiobook(): BelongsTo
    {
        return $this->belongsTo(Audiobook::class);
    }

    /**
     * The chapter where the customer is currently listening.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(
            AudiobookChapter::class,
            'audiobook_chapter_id'
        );
    }

    /**
     * Determine whether the audiobook has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->is_completed;
    }

    /**
     * Determine whether there is saved listening progress.
     */
    public function hasProgress(): bool
    {
        return $this->position_seconds > 0
            || $this->listened_seconds > 0;
    }

    /**
     * Get the current playback position in minutes.
     */
    public function positionInMinutes(): float
    {
        return round(
            $this->position_seconds / 60,
            2
        );
    }
}