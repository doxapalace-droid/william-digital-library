<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasUuid, SoftDeletes;

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
    ];

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
        ];
    }

    /**
     * Entitlements granted for this book.
     *
     * These determine which users are allowed to read
     * or download the book.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(BookEntitlement::class);
    }

    /**
     * Categories assigned to this book.
     *
     * A book may belong to multiple categories and
     * a category may contain multiple books.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withTimestamps();
    }
   


}