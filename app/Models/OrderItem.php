<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'order_id',
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
     * Order containing this item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Book being purchased.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Audiobook being purchased.
     */
    public function audiobook(): BelongsTo
    {
        return $this->belongsTo(Audiobook::class);
    }

    /**
     * Determine whether this order item is a book.
     */
    public function isBook(): bool
    {
        return $this->item_type === 'book';
    }

    /**
     * Determine whether this order item is an audiobook.
     */
    public function isAudiobook(): bool
    {
        return $this->item_type === 'audiobook';
    }
}