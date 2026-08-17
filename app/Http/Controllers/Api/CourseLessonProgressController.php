<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseLessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseLessonProgressController extends Controller
{
    /**
     * Get the authenticated user's progress
     * for an entire course.
     */
    public function course(
        Request $request,
        Course $course
    ): JsonResponse {
        $this->ensureCourseIsAvailable($course);

        $progress = CourseLessonProgress::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'course_id',
                $course->id
            )
            ->with([
                'lesson',
            ])
            ->orderBy('course_lesson_id')
            ->get();

        $totalLessons = CourseLesson::query()
            ->where(
                'course_id',
                $course->id
            )
            ->where(
                'status',
                'active'
            )
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

        $completedLessons = $progress
            ->where('completed', true)
            ->count();

        $remainingLessons = max(
            $totalLessons - $completedLessons,
            0
        );

        $progressPercentage = $totalLessons > 0
            ? round(
                (
                    $completedLessons
                    / $totalLessons
                ) * 100,
                2
            )
            : 0;

        return response()->json([
            'data' => $progress,

            'meta' => [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'remaining_lessons' => $remainingLessons,
                'progress_percentage' => $progressPercentage,
            ],
        ]);
    }

    /**
     * Get the authenticated user's progress
     * for a specific lesson.
     *
     * Progress itself does not require a course
     * entitlement. The lesson must simply belong
     * to an active course and be active/published.
     */
    public function show(
        Request $request,
        Course $course,
        CourseLesson $lesson
    ): JsonResponse {
        $this->ensureCourseIsAvailable($course);

        $this->ensureLessonBelongsToCourse(
            $course,
            $lesson
        );

        $this->ensureLessonIsAvailable(
            $lesson
        );

        $progress = CourseLessonProgress::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'course_id',
                $course->id
            )
            ->where(
                'course_lesson_id',
                $lesson->id
            )
            ->with([
                'lesson',
            ])
            ->first();

        return response()->json([
            'data' => $progress,
        ]);
    }

    /**
     * Create or update lesson progress.
     *
     * An authenticated user may save learning progress
     * for an active lesson.
     *
     * Course entitlement is required to VIEW a paid lesson,
     * but it is not required merely to store progress.
     */
    public function update(
        Request $request,
        Course $course,
        CourseLesson $lesson
    ): JsonResponse {
        $this->ensureCourseIsAvailable($course);

        $this->ensureLessonBelongsToCourse(
            $course,
            $lesson
        );

        $this->ensureLessonIsAvailable(
            $lesson
        );

        $validated = $request->validate([
            'position_seconds' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'completed' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $progress = CourseLessonProgress::query()
            ->firstOrNew([
                'user_id' => $request->user()->id,
                'course_id' => $course->id,
                'course_lesson_id' => $lesson->id,
            ]);

        if (
            array_key_exists(
                'position_seconds',
                $validated
            )
        ) {
            $progress->position_seconds =
                $validated['position_seconds'];
        }

        if (
            array_key_exists(
                'completed',
                $validated
            )
        ) {
            $progress->completed =
                $validated['completed'];

            if ($validated['completed']) {
                $progress->completed_at = now();
            } else {
                $progress->completed_at = null;
            }
        }

        $progress->last_accessed_at = now();

        $progress->save();

        $progress->load([
            'lesson',
        ]);

        return response()->json([
            'data' => $progress,
        ]);
    }

    /**
     * Mark a lesson as completed.
     */
    public function complete(
        Request $request,
        Course $course,
        CourseLesson $lesson
    ): JsonResponse {
        $this->ensureCourseIsAvailable($course);

        $this->ensureLessonBelongsToCourse(
            $course,
            $lesson
        );

        $this->ensureLessonIsAvailable(
            $lesson
        );

        $progress = CourseLessonProgress::query()
            ->firstOrNew([
                'user_id' => $request->user()->id,
                'course_id' => $course->id,
                'course_lesson_id' => $lesson->id,
            ]);

        $progress->markCompleted();

        $progress->load([
            'lesson',
        ]);

        return response()->json([
            'message' =>
                'Lesson completed successfully.',

            'data' => $progress,
        ]);
    }

    /**
     * Ensure the course is currently available.
     */
    private function ensureCourseIsAvailable(
        Course $course
    ): void {
        if (! $course->isActive()) {
            abort(
                404,
                'Course not found.'
            );
        }
    }

    /**
     * Ensure the lesson belongs to the supplied course.
     */
    private function ensureLessonBelongsToCourse(
        Course $course,
        CourseLesson $lesson
    ): void {
        if (
            $lesson->course_id !== $course->id
        ) {
            abort(
                404,
                'Lesson not found.'
            );
        }
    }

    /**
     * Ensure the lesson is active and published.
     *
     * Entitlement is deliberately NOT checked here.
     *
     * Entitlement controls access to the actual paid
     * lesson/video, not whether an authenticated user
     * can store learning-progress information.
     */
    private function ensureLessonIsAvailable(
        CourseLesson $lesson
    ): void {
        if (! $lesson->isActive()) {
            abort(
                404,
                'Lesson not found.'
            );
        }
    }
}