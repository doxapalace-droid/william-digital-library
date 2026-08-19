<?php

namespace App\Models;

use App\Traits\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role_id',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasUuid, SoftDeletes;

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
     * The audiobook entitlements belonging to this user.
     */
    public function audiobookEntitlements(): HasMany
    {
        return $this->hasMany(AudiobookEntitlement::class);
    }

    /**
     * The course entitlements belonging to this user.
     */
    public function courseEntitlements(): HasMany
    {
        return $this->hasMany(CourseEntitlement::class);
    }

    /**
     * Course lesson progress records belonging to this user.
     */
    public function courseLessonProgress(): HasMany
    {
        return $this->hasMany(CourseLessonProgress::class);
    }

    /**
     * Podcast listening progress records belonging to this user.
     */
    public function podcastEpisodeProgress(): HasMany
    {
        return $this->hasMany(
            PodcastEpisodeProgress::class,
            'user_id'
        );
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
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now()
                    );
            })
            ->exists();
    }

    /**
     * Determine whether the user can access the given course.
     */
    public function canAccessCourse(Course $course): bool
    {
        return $this->courseEntitlements()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->where('can_access', true)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now()
                    );
            })
            ->exists();
    }

    /**
     * Reading progress records belonging to this user.
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

    /**
     * Recently viewed books by this user.
     */
    public function recentlyViewed(): HasMany
    {
        return $this->hasMany(RecentlyViewed::class);
    }

    /**
     * Orders placed by the customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Items currently in the customer's cart.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Payments belonging to this user.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}