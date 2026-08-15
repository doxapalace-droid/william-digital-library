<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudiobookEntitlement extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'audiobook_id',
        'source',
        'can_stream',
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
            'can_stream'   => 'boolean',
            'can_download' => 'boolean',
            'granted_at'   => 'datetime',
            'expires_at'   => 'datetime',
            'revoked_at'   => 'datetime',
        ];
    }

    /**
     * Use UUID for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The customer who owns this entitlement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The audiobook this entitlement provides access to.
     */
    public function audiobook(): BelongsTo
    {
        return $this->belongsTo(Audiobook::class);
    }

    /**
     * Determine whether the entitlement is currently active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->revoked_at !== null) {
            return false;
        }

        if (
            $this->expires_at !== null
            && $this->expires_at->isPast()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the customer can stream
     * the audiobook.
     */
    public function canStream(): bool
    {
        return $this->isActive() && $this->can_stream;
    }

    /**
     * Determine whether the customer can download
     * the audiobook.
     */
    public function canDownload(): bool
    {
        return $this->isActive() && $this->can_download;
    }
}