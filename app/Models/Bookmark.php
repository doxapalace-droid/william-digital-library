<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bookmark extends Model
{
    use HasFactory;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'current_page',
        'location',
        'label',
        'note',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'current_page' => 'integer',
        ];
    }

    /**
     * The customer who created this bookmark.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The book containing this bookmark.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}