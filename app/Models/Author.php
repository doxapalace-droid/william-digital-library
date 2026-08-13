<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Author extends Model
{
    use HasFactory, HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'name',
        'slug',
        'bio',
        'photo',
        'is_active',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Books written by this author.
     *
     * A book may have multiple authors,
     * and an author may write multiple books.
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)
            ->withTimestamps();
    }
}