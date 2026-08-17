<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Public course catalogue returns active courses.
     */
    public function test_public_course_catalogue_returns_active_courses(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/courses');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $course->id)
            ->assertJsonPath('data.0.title', $course->title);
    }

    /**
     * Draft courses do not appear in the public catalogue.
     */
    public function test_draft_course_does_not_appear_in_catalogue(): void
    {
        Course::factory()->create([
            'status' => 'draft',
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/courses');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Inactive courses do not appear in the public catalogue.
     */
    public function test_inactive_course_does_not_appear_in_catalogue(): void
    {
        Course::factory()->create([
            'status' => 'inactive',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/courses');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Future courses do not appear in the public catalogue.
     */
    public function test_future_course_does_not_appear_in_catalogue(): void
    {
        Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/courses');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Active courses without a publication date are visible.
     */
    public function test_active_course_without_publication_date_is_visible(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/courses');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $course->id);
    }

    /**
     * Course catalogue is paginated.
     */
    public function test_course_catalogue_is_paginated(): void
    {
        Course::factory()
            ->count(15)
            ->create([
                'status' => 'active',
                'published_at' => now()->subDay(),
            ]);

        $response = $this->getJson(
            '/api/courses?per_page=5'
        );

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 15);
    }

    /**
     * Course catalogue limits per page to fifty.
     */
    public function test_course_catalogue_limits_per_page_to_fifty(): void
    {
        Course::factory()
            ->count(55)
            ->create([
                'status' => 'active',
                'published_at' => now()->subDay(),
            ]);

        $response = $this->getJson(
            '/api/courses?per_page=100'
        );

        $response
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 55);
    }

    /**
     * Public course details are available.
     */
    public function test_public_course_details_are_available(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.uuid', $course->uuid)
            ->assertJsonPath('data.title', $course->title);
    }

    /**
     * Course details use UUID route binding.
     */
    public function test_course_details_use_uuid_route_binding(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.uuid', $course->uuid);
    }

    /**
     * Draft course details return not found.
     */
    public function test_draft_course_details_return_not_found(): void
    {
        $course = Course::factory()->create([
            'status' => 'draft',
            'published_at' => null,
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertNotFound();
    }

    /**
     * Inactive course details return not found.
     */
    public function test_inactive_course_details_return_not_found(): void
    {
        $course = Course::factory()->create([
            'status' => 'inactive',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertNotFound();
    }

    /**
     * Future course details return not found.
     */
    public function test_future_course_details_return_not_found(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertNotFound();
    }

    /**
     * Nonexistent course returns not found.
     */
    public function test_nonexistent_course_returns_not_found(): void
    {
        $response = $this->getJson(
            '/api/courses/00000000-0000-0000-0000-000000000000'
        );

        $response
            ->assertNotFound();
    }

    /**
     * Public course details include active lessons.
     */
    public function test_course_details_include_active_lessons(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'lesson_number' => 1,
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.lessons.0.id',
                $lesson->id
            )
            ->assertJsonPath(
                'data.lessons.0.title',
                $lesson->title
            );
    }

    /**
     * Draft lessons do not appear in public course details.
     */
    public function test_draft_lesson_does_not_appear_in_course_details(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'status' => 'draft',
            'published_at' => null,
            'lesson_number' => 1,
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data.lessons');
    }

    /**
     * Inactive lessons do not appear in public course details.
     */
    public function test_inactive_lesson_does_not_appear_in_course_details(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'status' => 'inactive',
            'published_at' => now()->subDay(),
            'lesson_number' => 1,
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data.lessons');
    }

    /**
     * Future lessons do not appear in public course details.
     */
    public function test_future_lesson_does_not_appear_in_course_details(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'status' => 'active',
            'published_at' => now()->addDay(),
            'lesson_number' => 1,
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data.lessons');
    }

    /**
     * Course lessons are returned in lesson-number order.
     */
    public function test_course_lessons_are_ordered_by_lesson_number(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lessonThree = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'lesson_number' => 3,
        ]);

        $lessonOne = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'lesson_number' => 1,
        ]);

        $lessonTwo = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'lesson_number' => 2,
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.lessons.0.id',
                $lessonOne->id
            )
            ->assertJsonPath(
                'data.lessons.1.id',
                $lessonTwo->id
            )
            ->assertJsonPath(
                'data.lessons.2.id',
                $lessonThree->id
            );
    }

    /**
     * Course details include associated video information.
     */
    public function test_course_details_include_associated_video(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $video = Video::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'video_id' => $video->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'lesson_number' => 1,
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.lessons.0.video.id',
                $video->id
            );
    }

    /**
     * Public course response does not expose private
     * video file paths.
     */
    public function test_course_response_does_not_expose_private_video_file(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $video = Video::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
            'video_file' => 'private/videos/course-video.mp4',
        ]);

        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'video_id' => $video->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'lesson_number' => 1,
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonMissing([
                'video_file' => 'private/videos/course-video.mp4',
            ]);
    }

    /**
     * Guests can view the public course catalogue.
     */
    public function test_guest_can_view_course_catalogue(): void
    {
        Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/courses');

        $response
            ->assertOk();
    }
}