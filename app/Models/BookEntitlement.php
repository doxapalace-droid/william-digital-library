<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookEntitlement extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'source',
        'can_read',
        'can_download',
        'status',
        'granted_at',
        'expires_at',
        'revoked_at',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'can_read'      => 'boolean',
            'can_download'  => 'boolean',
            'granted_at'    => 'datetime',
            'expires_at'    => 'datetime',
            'revoked_at'    => 'datetime',
        ];
    }

    /**
     * The customer who owns this entitlement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The entitled book.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Determine whether the entitlement is active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the customer can read the book.
     */
    public function canRead(): bool
    {
        return $this->isActive() && $this->can_read;
    }

    /**
     * Determine whether the customer can download the book.
     */
    public function canDownload(): bool
    {
        return $this->isActive() && $this->can_download;
    }
}