<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseLessonProgress;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLessonProgressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a properly connected course structure
     * for progress tests.
     */
    private function createLessonStructure(): array
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
        ]);

        return [
            $user,
            $course,
            $lesson,
        ];
    }

    /**
     * Progress can be created.
     */
    public function test_progress_can_be_created(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'position_seconds' => 125,
        ]);

        $this->assertDatabaseHas('course_lesson_progress', [
            'id' => $progress->id,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'position_seconds' => 125,
        ]);
    }

    /**
     * Progress has a UUID.
     */
    public function test_progress_has_uuid(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->assertNotNull($progress->uuid);
        $this->assertIsString($progress->uuid);
    }

    /**
     * Progress uses UUID for route model binding.
     */
    public function test_progress_uses_uuid_for_route_binding(): void
    {
        $progress = new CourseLessonProgress();

        $this->assertSame(
            'uuid',
            $progress->getRouteKeyName()
        );
    }

    /**
     * Progress belongs to a user.
     */
    public function test_progress_belongs_to_user(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->assertTrue(
            $progress->user->is($user)
        );
    }

    /**
     * Progress belongs to a course.
     */
    public function test_progress_belongs_to_course(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->assertTrue(
            $progress->course->is($course)
        );
    }

    /**
     * Progress belongs to a lesson.
     */
    public function test_progress_belongs_to_lesson(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->assertTrue(
            $progress->lesson->is($lesson)
        );
    }

    /**
     * Position is cast to integer.
     */
    public function test_position_seconds_is_cast_to_integer(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'position_seconds' => '245',
        ]);

        $this->assertIsInt(
            $progress->position_seconds
        );

        $this->assertSame(
            245,
            $progress->position_seconds
        );
    }

    /**
     * Completed is cast to boolean.
     */
    public function test_completed_is_cast_to_boolean(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'completed' => 1,
        ]);

        $this->assertIsBool(
            $progress->completed
        );

        $this->assertTrue(
            $progress->completed
        );
    }

    /**
     * Completed at is cast to datetime.
     */
    public function test_completed_at_is_cast_to_datetime(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'completed_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $progress->completed_at
        );
    }

    /**
     * Last accessed at is cast to datetime.
     */
    public function test_last_accessed_at_is_cast_to_datetime(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'last_accessed_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $progress->last_accessed_at
        );
    }

    /**
     * Incomplete progress is identified correctly.
     */
    public function test_incomplete_progress_is_not_completed(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()
            ->incomplete()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'course_lesson_id' => $lesson->id,
            ]);

        $this->assertFalse(
            $progress->isCompleted()
        );

        $this->assertFalse(
            $progress->completed
        );
    }

    /**
     * Completed progress is identified correctly.
     */
    public function test_completed_progress_is_identified_correctly(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()
            ->completed()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'course_lesson_id' => $lesson->id,
            ]);

        $this->assertTrue(
            $progress->isCompleted()
        );

        $this->assertTrue(
            $progress->completed
        );

        $this->assertNotNull(
            $progress->completed_at
        );
    }

    /**
     * markCompleted() updates the progress record.
     */
    public function test_mark_completed_updates_progress(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()
            ->incomplete()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'course_lesson_id' => $lesson->id,
                'position_seconds' => 500,
                'completed_at' => null,
            ]);

        $progress->markCompleted();

        $progress->refresh();

        $this->assertTrue(
            $progress->completed
        );

        $this->assertNotNull(
            $progress->completed_at
        );

        $this->assertNotNull(
            $progress->last_accessed_at
        );

        $this->assertTrue(
            $progress->isCompleted()
        );
    }

    /**
     * Factory atPosition() stores the requested position.
     */
    public function test_at_position_factory_state_works(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        $progress = CourseLessonProgress::factory()
            ->atPosition(375)
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'course_lesson_id' => $lesson->id,
            ]);

        $this->assertSame(
            375,
            $progress->position_seconds
        );

        $this->assertFalse(
            $progress->completed
        );

        $this->assertNull(
            $progress->completed_at
        );
    }

    /**
     * A user cannot have duplicate progress records
     * for the same lesson.
     */
    public function test_duplicate_user_lesson_progress_is_rejected(): void
    {
        [$user, $course, $lesson] = $this->createLessonStructure();

        CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->expectException(QueryException::class);

        CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);
    }

    /**
     * The same user can have progress for different lessons.
     */
    public function test_user_can_have_progress_for_different_lessons(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        $lessonOne = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'lesson_number' => 1,
        ]);

        $lessonTwo = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'lesson_number' => 2,
        ]);

        CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lessonOne->id,
        ]);

        CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lessonTwo->id,
        ]);

        $this->assertCount(
            2,
            CourseLessonProgress::where('user_id', $user->id)->get()
        );
    }

    /**
     * Different users can have progress for the same lesson.
     */
    public function test_different_users_can_have_progress_for_same_lesson(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $course = Course::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
        ]);

        CourseLessonProgress::factory()->create([
            'user_id' => $userOne->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        CourseLessonProgress::factory()->create([
            'user_id' => $userTwo->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->assertCount(
            2,
            CourseLessonProgress::where(
                'course_lesson_id',
                $lesson->id
            )->get()
        );
    }
}