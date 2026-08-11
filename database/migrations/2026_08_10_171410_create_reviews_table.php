<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'rating',
        'review',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Reviewer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Reviewed book.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Scope reviews with a specific rating.
     */
    public function scopeWithRating(
        Builder $query,
        int $rating
    ): Builder {
        return $query->where('rating', $rating);
    }
}