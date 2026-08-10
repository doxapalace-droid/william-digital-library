<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Highlight extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'current_page',
        'location',
        'selected_text',
        'note',
        'color',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'current_page' => 'integer',
        ];
    }

    /**
     * The customer who created the highlight.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The book containing the highlight.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}