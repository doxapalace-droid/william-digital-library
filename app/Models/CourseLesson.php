<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseLesson extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'course_id',
        'video_id',
        'title',
        'slug',
        'description',
        'lesson_number',
        'status',
        'is_preview',
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
     * Course this lesson belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Video attached to this lesson.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Progress records for this lesson.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(
            CourseLessonProgress::class,
            'course_lesson_id'
        );
    }

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'lesson_number' => 'integer',
            'is_preview' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the lesson is currently active.
     *
     * A lesson is active only when:
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
     * Determine whether the lesson can be viewed
     * as a free preview.
     */
    public function isPreview(): bool
    {
        return $this->is_preview;
    }

    /**
     * Determine whether the lesson has an available video.
     */
    public function hasVideo(): bool
    {
        return $this->video !== null;
    }
}