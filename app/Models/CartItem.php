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

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'item_type',
        'book_id',
        'audiobook_id',
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
     * Model booting.
     *
     * Automatically determine the item type when
     * application code creates a CartItem without
     * explicitly providing item_type.
     *
     * This is a safety net.
     *
     * Controllers should still explicitly provide
     * item_type when creating cart items.
     */
    protected static function booted(): void
    {
        static::creating(function (CartItem $cartItem): void {
            /*
             * If the caller already supplied a valid
             * item type, preserve it.
             */
            if (
                in_array(
                    $cartItem->item_type,
                    [
                        self::TYPE_BOOK,
                        self::TYPE_AUDIOBOOK,
                    ],
                    true
                )
            ) {
                return;
            }

            /*
             * Determine the type from the associated
             * product when item_type was omitted.
             */
            if (
                $cartItem->book_id !== null
                && $cartItem->audiobook_id === null
            ) {
                $cartItem->item_type = self::TYPE_BOOK;

                return;
            }

            if (
                $cartItem->audiobook_id !== null
                && $cartItem->book_id === null
            ) {
                $cartItem->item_type = self::TYPE_AUDIOBOOK;

                return;
            }

            /*
             * A cart item must represent exactly one
             * product type.
             *
             * Do not allow an ambiguous cart item to
             * reach the database.
             */
            throw new InvalidArgumentException(
                'A cart item must contain exactly one of book_id or audiobook_id.'
            );
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
     * Calculate the subtotal from the current
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
}