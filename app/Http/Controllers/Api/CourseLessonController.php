<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseLessonController extends Controller
{
    /**
     * Display a course lesson.
     *
     * Access rules:
     *
     * 1. The course must be active and publicly available.
     * 2. The lesson must belong to the requested course.
     * 3. The lesson must be active and currently published.
     * 4. Preview lessons are accessible to everyone.
     * 5. Non-preview lessons require an active course entitlement.
     * 6. Private video file paths are never exposed.
     */
    public function show(
        Request $request,
        Course $course,
        CourseLesson $lesson
    ): JsonResponse {
        /*
         * The course itself must be publicly available.
         */
        if (! $course->isActive()) {
            abort(
                404,
                'Course not found.'
            );
        }

        /*
         * Prevent a lesson belonging to another course
         * from being accessed through this course.
         */
        if ($lesson->course_id !== $course->id) {
            abort(
                404,
                'Lesson not found.'
            );
        }

        /*
         * The lesson must currently be active and published.
         */
        if (! $lesson->isActive()) {
            abort(
                404,
                'Lesson not found.'
            );
        }

        /*
         * Preview lessons are publicly accessible.
         */
        if (! $lesson->isPreview()) {
            /*
             * Non-preview lessons require authentication.
             */
            $user = $request->user();

            if (! $user) {
                abort(
                    401,
                    'Authentication is required to access this lesson.'
                );
            }

            /*
             * The authenticated customer must have
             * an active entitlement for this course.
             */
            if (! $user->canAccessCourse($course)) {
                abort(
                    403,
                    'You do not have access to this course.'
                );
            }
        }

        /*
         * Load only public video information.
         *
         * The private video_file field is deliberately
         * excluded from the query.
         */
        $lesson->load([
            'video' => function ($query) {
                $query->select([
                    'id',
                    'uuid',
                    'title',
                    'slug',
                    'description',
                    'cover_image',
                    'price',
                    'currency',
                    'status',
                    'duration_seconds',
                    'published_at',
                ]);
            },
        ]);

        return response()->json([
            'data' => $lesson,
        ]);
    }
}