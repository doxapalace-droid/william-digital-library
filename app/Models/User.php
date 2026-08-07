<?php

namespace App\Models;

use App\Traits\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'email', 'password', 'role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use  HasApiTokens, HasFactory, Notifiable, HasUuid, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The role assigned to this user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The book entitlements belonging to this user.
     */
    public function bookEntitlements(): HasMany
    {
        return $this->hasMany(BookEntitlement::class);
    }

    /**
     * Determine whether the user can read the given book.
     */
    public function canReadBook(Book $book): bool
    {
        return $this->bookEntitlements()
            ->where('book_id', $book->id)
            ->where('status', 'active')
            ->where('can_read', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
 * Reading progress records belonging to this user.
 *
 * Each record tracks the user's current position
 * and progress in a particular book.
 */
    public function readingProgress(): HasMany
    {
    return $this->hasMany(ReadingProgress::class);
    }


        /**
 * Bookmarks created by this customer.
 */
    public function bookmarks(): HasMany
    {
    return $this->hasMany(Bookmark::class);
    }


        /**
 * Highlights created by this user.
 */
    public function highlights(): HasMany
    {
    return $this->hasMany(Highlight::class);
    }
        
    /**
 * Reading notes created by this user.
 */
    public function readingNotes(): HasMany 
    {
    return $this->hasMany(ReadingNote::class);
    }


    /**
 * The reader preferences belonging to this user.
 */
    public function readerPreference(): HasOne
    {
    return $this->hasOne(ReaderPreference::class);
    }


    /**
 * Books favorited by this user.
 */
    public function favorites(): HasMany
    {
    return $this->hasMany(Favorite::class);
    }

}