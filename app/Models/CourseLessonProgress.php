<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLessonProgress extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'course_lesson_id',
        'position_seconds',
        'completed',
        'completed_at',
        'last_accessed_at',
    ];

    /**
     * Use UUID for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * User who owns this progress record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Course this progress belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Lesson this progress belongs to.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(
            CourseLesson::class,
            'course_lesson_id'
        );
    }

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'position_seconds' => 'integer',
            'completed' => 'boolean',
            'completed_at' => 'datetime',
            'last_accessed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the lesson has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed === true;
    }

    /**
     * Mark the lesson as completed.
     *
     * This method works for both existing and
     * newly-created progress records.
     */
    public function markCompleted(): void
    {
        $now = now();

        $this->completed = true;
        $this->completed_at = $now;
        $this->last_accessed_at = $now;

        $this->save();
    }
}