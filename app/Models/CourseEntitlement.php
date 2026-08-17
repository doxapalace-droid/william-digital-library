<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEntitlement extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'source',
        'can_access',
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
            'can_access' => 'boolean',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
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
     * The course this entitlement belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
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

        if (
            $this->expires_at !== null
            && $this->expires_at->isPast()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the customer can access
     * the course.
     */
    public function canAccess(): bool
    {
        return $this->isActive()
            && $this->can_access;
    }
}