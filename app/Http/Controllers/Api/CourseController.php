<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * Display the public course catalogue.
     *
     * Only active and currently published courses
     * are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max(
                (int) $request->input('per_page', 12),
                1
            ),
            50
        );

        $courses = Course::query()
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
            ->withCount([
                'lessons' => function ($query) {
                    $query
                        ->where('status', 'active')
                        ->where(function ($lessonQuery) {
                            $lessonQuery
                                ->whereNull('published_at')
                                ->orWhere(
                                    'published_at',
                                    '<=',
                                    now()
                                );
                        });
                },
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $courses->items(),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    /**
     * Display a single public course.
     *
     * Only active and currently published courses
     * are publicly accessible.
     *
     * Only public video information is returned
     * for each lesson.
     */
    public function show(
        Course $course
    ): JsonResponse {
        $this->ensurePubliclyAvailable($course);

        $course->load([
            'lessons' => function ($query) {
                $query
                    ->where('status', 'active')
                    ->where(function ($lessonQuery) {
                        $lessonQuery
                            ->whereNull('published_at')
                            ->orWhere(
                                'published_at',
                                '<=',
                                now()
                            );
                    })
                    ->with([
                        'video' => function ($videoQuery) {
                            $videoQuery->select([
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
                    ])
                    ->orderBy('lesson_number');
            },
        ]);

        return response()->json([
            'data' => $course,
        ]);
    }

    /**
     * Ensure that a course is publicly available.
     */
    private function ensurePubliclyAvailable(
        Course $course
    ): void {
        if (! $course->isActive()) {
            abort(
                404,
                'Course not found.'
            );
        }
    }
}