<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingProgress extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'reading_progress';

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'current_page',
        'total_pages',
        'location',
        'progress_percentage',
        'last_read_at',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'current_page' => 'integer',
            'total_pages' => 'integer',
            'progress_percentage' => 'float',
            'last_read_at' => 'datetime',
        ];
    }

    /**
     * The customer whose reading progress this is.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The book being read.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}