<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'title',
        'slug',
        'subtitle',
        'description',
        'cover_image',
        'price',
        'currency',
        'status',
        'published_at',
    ];

    /**
     * Use UUID for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Lessons belonging to this course.
     *
     * Lessons are ordered by their lesson number.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)
            ->orderBy('lesson_number');
    }

    /**
     * Entitlements granted for this course.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(CourseEntitlement::class);
    }

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the course is currently active.
     *
     * A course is active only when:
     *
     * 1. Its status is active.
     * 2. It is not scheduled for future publication.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
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
     * Determine whether the course is available
     * for purchase.
     */
    public function isPurchasable(): bool
    {
        return $this->isActive()
            && (float) $this->price >= 0;
    }

    /**
     * Get the number of lessons in the course.
     */
    public function lessonsCount(): int
    {
        return $this->lessons()->count();
    }

    /**
     * Get the number of published lessons.
     */
    public function publishedLessonsCount(): int
    {
        return $this->lessons()
            ->where('status', 'active')
            ->where(function ($query) {
                $query
                    ->whereNull('published_at')
                    ->orWhere(
                        'published_at',
                        '<=',
                        now()
                    );
            })
            ->count();
    }

    /**
     * Get the course price as a formatted string.
     */
    public function formattedPrice(): string
    {
        return number_format(
            (float) $this->price,
            2,
            '.',
            ''
        );
    }
}