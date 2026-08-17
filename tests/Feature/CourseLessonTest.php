<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLessonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A course lesson can be created.
     */
    public function test_course_lesson_can_be_created(): void
    {
        $lesson = CourseLesson::factory()->create();

        $this->assertDatabaseHas('course_lessons', [
            'id' => $lesson->id,
            'uuid' => $lesson->uuid,
            'course_id' => $lesson->course_id,
            'video_id' => $lesson->video_id,
            'title' => $lesson->title,
        ]);
    }

    /**
     * A course lesson has a UUID.
     */
    public function test_course_lesson_has_uuid(): void
    {
        $lesson = CourseLesson::factory()->create();

        $this->assertNotNull($lesson->uuid);
        $this->assertIsString($lesson->uuid);
    }

    /**
     * UUID is used for route model binding.
     */
    public function test_course_lesson_uses_uuid_for_route_binding(): void
    {
        $lesson = CourseLesson::factory()->create();

        $this->assertSame(
            'uuid',
            $lesson->getRouteKeyName()
        );
    }

    /**
     * A lesson belongs to a course.
     */
    public function test_course_lesson_belongs_to_course(): void
    {
        $course = Course::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
        ]);

        $this->assertTrue(
            $lesson->course->is($course)
        );
    }

    /**
     * A lesson belongs to a video.
     */
    public function test_course_lesson_belongs_to_video(): void
    {
        $video = Video::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'video_id' => $video->id,
        ]);

        $this->assertTrue(
            $lesson->video->is($video)
        );
    }

    /**
     * A course can retrieve its lessons.
     */
    public function test_course_has_lessons(): void
    {
        $course = Course::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
        ]);

        $this->assertTrue(
            $course->lessons->contains($lesson)
        );
    }

    /**
     * Lesson number is cast to integer.
     */
    public function test_lesson_number_is_cast_to_integer(): void
    {
        $lesson = CourseLesson::factory()->create([
            'lesson_number' => '5',
        ]);

        $this->assertSame(
            5,
            $lesson->lesson_number
        );
    }

    /**
     * Preview value is cast to boolean.
     */
    public function test_is_preview_is_cast_to_boolean(): void
    {
        $lesson = CourseLesson::factory()->create([
            'is_preview' => 1,
        ]);

        $this->assertTrue(
            $lesson->is_preview
        );

        $this->assertIsBool(
            $lesson->is_preview
        );
    }

    /**
     * Published date is cast to datetime.
     */
    public function test_published_at_is_cast_to_datetime(): void
    {
        $lesson = CourseLesson::factory()->create([
            'published_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $lesson->published_at
        );
    }

    /**
     * An active published lesson is active.
     */
    public function test_active_published_lesson_is_active(): void
    {
        $lesson = CourseLesson::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->assertTrue(
            $lesson->isActive()
        );
    }

    /**
     * A draft lesson is not active.
     */
    public function test_draft_lesson_is_not_active(): void
    {
        $lesson = CourseLesson::factory()
            ->draft()
            ->create();

        $this->assertFalse(
            $lesson->isActive()
        );
    }

    /**
     * An inactive lesson is not active.
     */
    public function test_inactive_lesson_is_not_active(): void
    {
        $lesson = CourseLesson::factory()
            ->inactive()
            ->create();

        $this->assertFalse(
            $lesson->isActive()
        );
    }

    /**
     * A future-published lesson is not active.
     */
    public function test_future_lesson_is_not_active(): void
    {
        $lesson = CourseLesson::factory()
            ->future()
            ->create();

        $this->assertFalse(
            $lesson->isActive()
        );
    }

    /**
     * An active lesson without a publication date is active.
     */
    public function test_active_lesson_without_publication_date_is_active(): void
    {
        $lesson = CourseLesson::factory()->create([
            'status' => 'active',
            'published_at' => null,
        ]);

        $this->assertTrue(
            $lesson->isActive()
        );
    }

    /**
     * A preview lesson is identified correctly.
     */
    public function test_preview_lesson_is_identified_correctly(): void
    {
        $lesson = CourseLesson::factory()
            ->preview()
            ->create();

        $this->assertTrue(
            $lesson->isPreview()
        );
    }

    /**
     * A normal lesson is not a preview.
     */
    public function test_normal_lesson_is_not_a_preview(): void
    {
        $lesson = CourseLesson::factory()->create([
            'is_preview' => false,
        ]);

        $this->assertFalse(
            $lesson->isPreview()
        );
    }

    /**
     * A lesson with a video reports that it has a video.
     */
    public function test_lesson_with_video_has_video(): void
    {
        $lesson = CourseLesson::factory()->create();

        $lesson->load('video');

        $this->assertTrue(
            $lesson->hasVideo()
        );
    }

    /**
     * Lessons are ordered by lesson number through the
     * Course relationship.
     */
    public function test_course_lessons_are_ordered_by_lesson_number(): void
    {
        $course = Course::factory()->create();

        $lessonThree = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'lesson_number' => 3,
        ]);

        $lessonOne = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'lesson_number' => 1,
        ]);

        $lessonTwo = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'lesson_number' => 2,
        ]);

        $lessons = $course->lessons()->get();

        $this->assertSame(
            [
                $lessonOne->id,
                $lessonTwo->id,
                $lessonThree->id,
            ],
            $lessons->pluck('id')->all()
        );
    }

    /**
     * A lesson can use a custom lesson number factory state.
     */
    public function test_numbered_factory_state_works(): void
    {
        $lesson = CourseLesson::factory()
            ->numbered(7)
            ->create();

        $this->assertSame(
            7,
            $lesson->lesson_number
        );
    }
}