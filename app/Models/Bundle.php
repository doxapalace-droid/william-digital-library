<?php

namespace App\Models;

use App\Traits\HasUuid;
use Database\Factories\BundleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bundle extends Model
{
    /** @use HasFactory<BundleFactory> */
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image',
        'price',
        'currency',
        'is_active',
        'is_published',
        'published_at',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Use UUID for public route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Products contained in this bundle.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }

    /**
     * Determine whether the bundle is currently active
     * and publicly available.
     */
    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->is_published) {
            return false;
        }

        if (
            $this->published_at !== null
            && $this->published_at->isFuture()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the bundle can currently
     * be purchased.
     */
    public function isPurchasable(): bool
    {
        return $this->isActive();
    }

    /**
     * Determine whether this is a free bundle.
     */
    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /**
     * Return the formatted bundle price.
     */
    public function formattedPrice(): string
    {
        return number_format(
            (float) $this->price,
            2
        );
    }

    /**
     * Number of products contained in the bundle.
     */
    public function itemsCount(): int
    {
        if ($this->relationLoaded('items')) {
            return $this->items->count();
        }

        return $this->items()->count();
    }
}