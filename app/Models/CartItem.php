<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class CartItem extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Supported cart item types.
     */
    public const TYPE_BOOK = 'book';

    public const TYPE_AUDIOBOOK = 'audiobook';

    public const TYPE_COURSE = 'course';

    public const TYPE_BUNDLE = 'bundle';

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'item_type',
        'book_id',
        'audiobook_id',
        'course_id',
        'bundle_id',
        'unit_price',
        'currency',
        'quantity',
        'subtotal',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * Automatically determine the cart item type
     * when it has not been explicitly supplied.
     */
    protected static function booted(): void
    {
        static::creating(function (CartItem $cartItem): void {
            /*
             * Preserve a valid explicitly supplied type.
             */
            if (
                in_array(
                    $cartItem->item_type,
                    [
                        self::TYPE_BOOK,
                        self::TYPE_AUDIOBOOK,
                        self::TYPE_COURSE,
                        self::TYPE_BUNDLE,
                    ],
                    true
                )
            ) {
                return;
            }

            /*
             * Determine the type from the associated
             * product.
             */
            $productIds = [
                self::TYPE_BOOK => $cartItem->book_id,
                self::TYPE_AUDIOBOOK => $cartItem->audiobook_id,
                self::TYPE_COURSE => $cartItem->course_id,
                self::TYPE_BUNDLE => $cartItem->bundle_id,
            ];

            $provided = collect($productIds)
                ->filter(
                    fn ($value) => $value !== null
                );

            if ($provided->count() !== 1) {
                throw new InvalidArgumentException(
                    'A cart item must contain exactly one of book_id, audiobook_id, course_id, or bundle_id.'
                );
            }

            $cartItem->item_type = $provided
                ->keys()
                ->first();
        });
    }

    /**
     * Customer who owns this cart item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Book contained in the cart.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Audiobook contained in the cart.
     */
    public function audiobook(): BelongsTo
    {
        return $this->belongsTo(Audiobook::class);
    }

    /**
     * Course contained in the cart.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Bundle contained in the cart.
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    /**
     * Calculate subtotal from the current
     * unit price and quantity.
     */
    public function calculateSubtotal(): float
    {
        return round(
            (float) $this->unit_price * $this->quantity,
            2
        );
    }

    /**
     * Determine whether this cart item is a book.
     */
    public function isBook(): bool
    {
        return $this->item_type === self::TYPE_BOOK;
    }

    /**
     * Determine whether this cart item is an audiobook.
     */
    public function isAudiobook(): bool
    {
        return $this->item_type === self::TYPE_AUDIOBOOK;
    }

    /**
     * Determine whether this cart item is a course.
     */
    public function isCourse(): bool
    {
        return $this->item_type === self::TYPE_COURSE;
    }

    /**
     * Determine whether this cart item is a bundle.
     */
    public function isBundle(): bool
    {
        return $this->item_type === self::TYPE_BUNDLE;
    }
}