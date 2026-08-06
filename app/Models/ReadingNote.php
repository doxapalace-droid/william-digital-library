<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingNote extends Model
{
    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'current_page',
        'location',
        'note',
    ];

    /**
     * The user who created this reading note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The book this reading note belongs to.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}