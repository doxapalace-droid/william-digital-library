<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'title',
        'slug',
        'subtitle',
        'description',
        'author',
        'isbn',
        'cover_image',
        'ebook_file',
        'pdf_path',
        'price',
        'currency',
        'is_featured',
        'is_published',
        'published_at',
        'average_rating',
        'reviews_count',
    ];

    /**
     * Use UUID for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Authors who wrote this book.
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)
            ->withTimestamps();
    }

    /**
     * Categories assigned to this book.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withTimestamps();
    }

    /**
     * Entitlements granted for this book.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(BookEntitlement::class);
    }

    /**
     * Reading progress records for this book.
     */
    public function readingProgress(): HasMany
    {
        return $this->hasMany(ReadingProgress::class);
    }

    /**
     * Bookmarks customers have created for this book.
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Highlights created for this book.
     */
    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    /**
     * Reading notes created by customers for this book.
     */
    public function readingNotes(): HasMany
    {
        return $this->hasMany(ReadingNote::class);
    }

    /**
     * Users' favorite records for this book.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Reviews submitted for this book.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Order items containing this book.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Cart items containing this book.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'average_rating' => 'float',
            'reviews_count' => 'integer',
        ];
    }
}