<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'order_id',
        'user_id',
        'gateway',
        'transaction_reference',
        'status',
        'currency',
        'amount',
        'gateway_response',
        'paid_at',
        'failed_at',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',

            /*
             * Paystack returns structured gateway data.
             * Store it as JSON in the database and expose it
             * as an array in PHP.
             */
            'gateway_response' => 'array',

            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Order associated with this payment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Customer who made this payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether the payment was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    /**
     * Determine whether the payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Determine whether the payment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}