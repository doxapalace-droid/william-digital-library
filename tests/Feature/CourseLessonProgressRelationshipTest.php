<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseLessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLessonProgressRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User has course lesson progress records.
     */
    public function test_user_has_course_lesson_progress(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
        ]);

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->assertTrue(
            $user->courseLessonProgress
                ->contains($progress)
        );
    }

    /**
     * Course has lesson progress records.
     */
    public function test_course_has_lesson_progress(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
        ]);

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->assertTrue(
            $course->lessonProgress
                ->contains($progress)
        );
    }

    /**
     * Course lesson has progress records.
     */
    public function test_course_lesson_has_progress(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
        ]);

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->assertTrue(
            $lesson->progress
                ->contains($progress)
        );
    }

    /**
     * Progress correctly belongs to the same user,
     * course, and lesson.
     */
    public function test_progress_relationships_are_consistent(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
        ]);

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->assertTrue(
            $progress->user->is($user)
        );

        $this->assertTrue(
            $progress->course->is($course)
        );

        $this->assertTrue(
            $progress->lesson->is($lesson)
        );
    }

    /**
     * Progress belonging to another user does not appear
     * in the current user's progress relationship.
     */
    public function test_users_only_see_their_own_progress_relationship_records(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $course = Course::factory()->create();

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
        ]);

        $progressOne = CourseLessonProgress::factory()->create([
            'user_id' => $userOne->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $progressTwo = CourseLessonProgress::factory()->create([
            'user_id' => $userTwo->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $userOneProgress = $userOne
            ->courseLessonProgress
            ->pluck('id');

        $this->assertTrue(
            $userOneProgress->contains($progressOne->id)
        );

        $this->assertFalse(
            $userOneProgress->contains($progressTwo->id)
        );
    }
}