<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\CourseLesson;
use App\Models\CourseLessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLessonProgressControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createCourse(
        array $attributes = []
    ): Course {
        return Course::factory()->create(
            array_merge([
                'status' => 'active',
                'published_at' => now()->subDay(),
            ], $attributes)
        );
    }

    private function createLesson(
        Course $course,
        array $attributes = []
    ): CourseLesson {
        return CourseLesson::factory()->create(
            array_merge([
                'course_id' => $course->id,
                'status' => 'active',
                'is_preview' => false,
                'published_at' => now()->subDay(),
            ], $attributes)
        );
    }

    private function grantCourseAccess(
        User $user,
        Course $course,
        array $attributes = []
    ): CourseEntitlement {
        return CourseEntitlement::factory()->create(
            array_merge([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => 'active',
                'can_access' => true,
                'revoked_at' => null,
                'expires_at' => null,
            ], $attributes)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Course Progress
    |--------------------------------------------------------------------------
    */

    public function test_authenticated_user_can_retrieve_course_progress(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson(
            $course,
            [
                'lesson_number' => 1,
            ]
        );

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'position_seconds' => 120,
            'completed' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/progress"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.uuid',
                $progress->uuid
            )
            ->assertJsonPath(
                'data.0.position_seconds',
                120
            )
            ->assertJsonPath(
                'meta.total_lessons',
                1
            )
            ->assertJsonPath(
                'meta.completed_lessons',
                0
            )
            ->assertJsonPath(
                'meta.progress_percentage',
                0
            );
    }

    public function test_user_cannot_see_another_users_course_progress(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $otherProgress = CourseLessonProgress::factory()->create([
            'user_id' => $otherUser->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/progress"
            );

        $response
            ->assertOk()
            ->assertJsonMissing([
                'uuid' => $otherProgress->uuid,
            ]);
    }

    public function test_course_progress_calculates_completion_percentage(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lessonOne = $this->createLesson(
            $course,
            [
                'lesson_number' => 1,
            ]
        );

        $lessonTwo = $this->createLesson(
            $course,
            [
                'lesson_number' => 2,
            ]
        );

        CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lessonOne->id,
            'completed' => true,
            'completed_at' => now(),
        ]);

        CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lessonTwo->id,
            'completed' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/progress"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total_lessons',
                2
            )
            ->assertJsonPath(
                'meta.completed_lessons',
                1
            )
            ->assertJsonPath(
                'meta.remaining_lessons',
                1
            )
            ->assertJsonPath(
                'meta.progress_percentage',
                50
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Individual Lesson Progress
    |--------------------------------------------------------------------------
    */

    public function test_authenticated_user_can_retrieve_lesson_progress(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'position_seconds' => 245,
            'completed' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $progress->uuid
            )
            ->assertJsonPath(
                'data.position_seconds',
                245
            );
    }

    public function test_lesson_without_progress_returns_null(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data',
                null
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Progress
    |--------------------------------------------------------------------------
    */

    public function test_user_can_save_lesson_progress(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => 180,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                180
            );

        $this->assertDatabaseHas(
            'course_lesson_progress',
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'course_lesson_id' => $lesson->id,
                'position_seconds' => 180,
            ]
        );
    }

    public function test_updating_progress_updates_existing_record(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $progress = CourseLessonProgress::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'position_seconds' => 100,
            'completed' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => 300,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $progress->uuid
            )
            ->assertJsonPath(
                'data.position_seconds',
                300
            );

        $this->assertDatabaseCount(
            'course_lesson_progress',
            1
        );
    }

    public function test_saving_progress_updates_last_accessed_at(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => 90,
                ]
            )
            ->assertOk();

        $progress = CourseLessonProgress::query()
            ->where('user_id', $user->id)
            ->where('course_lesson_id', $lesson->id)
            ->first();

        $this->assertNotNull($progress);

        $this->assertNotNull(
            $progress->last_accessed_at
        );
    }

    public function test_position_seconds_must_be_an_integer(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => -10,
                ]
            );

        $response->assertUnprocessable();
    }

    /*
    |--------------------------------------------------------------------------
    | Complete Lesson
    |--------------------------------------------------------------------------
    */

    public function test_user_can_complete_lesson(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/complete"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.completed',
                true
            );

        $this->assertDatabaseHas(
            'course_lesson_progress',
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'course_lesson_id' => $lesson->id,
                'completed' => true,
            ]
        );

        $progress = CourseLessonProgress::query()
            ->where('user_id', $user->id)
            ->where('course_lesson_id', $lesson->id)
            ->first();

        $this->assertNotNull($progress);

        $this->assertNotNull(
            $progress->completed_at
        );
    }

    public function test_completing_lesson_updates_last_accessed_at(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/complete"
            )
            ->assertOk();

        $progress = CourseLessonProgress::query()
            ->where('user_id', $user->id)
            ->where('course_lesson_id', $lesson->id)
            ->first();

        $this->assertNotNull($progress);

        $this->assertNotNull(
            $progress->last_accessed_at
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_course_progress(): void
    {
        $course = $this->createCourse();

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/progress"
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_access_lesson_progress(): void
    {
        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress"
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_update_lesson_progress(): void
    {
        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $response = $this->putJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
            [
                'position_seconds' => 100,
            ]
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_complete_lesson(): void
    {
        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $response = $this->postJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/complete"
        );

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Course Access
    |--------------------------------------------------------------------------
    */

    public function test_user_with_active_entitlement_can_save_paid_lesson_progress(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson(
            $course,
            [
                'is_preview' => false,
            ]
        );

        $this->grantCourseAccess(
            $user,
            $course
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => 240,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                240
            );
    }

    public function test_user_without_entitlement_can_save_paid_lesson_progress(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson(
            $course,
            [
                'is_preview' => false,
            ]
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => 240,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                240
            );
    }

    public function test_authenticated_user_can_save_preview_lesson_progress_without_entitlement(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson(
            $course,
            [
                'is_preview' => true,
            ]
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => 60,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                60
            );
    }

    public function test_expired_entitlement_does_not_prevent_progress_tracking(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $this->grantCourseAccess(
            $user,
            $course,
            [
                'expires_at' => now()->subMinute(),
            ]
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => 100,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                100
            );
    }

    public function test_revoked_entitlement_does_not_prevent_progress_tracking(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $this->grantCourseAccess(
            $user,
            $course,
            [
                'revoked_at' => now(),
            ]
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => 100,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                100
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Course / Lesson Availability
    |--------------------------------------------------------------------------
    */

    public function test_inactive_course_returns_not_found(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse([
            'status' => 'inactive',
        ]);

        $lesson = $this->createLesson($course);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress"
            );

        $response->assertNotFound();
    }

    public function test_future_course_returns_not_found(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse([
            'published_at' => now()->addDay(),
        ]);

        $lesson = $this->createLesson($course);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress"
            );

        $response->assertNotFound();
    }

    public function test_inactive_lesson_returns_not_found(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson(
            $course,
            [
                'status' => 'inactive',
            ]
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress"
            );

        $response->assertNotFound();
    }

    public function test_future_lesson_returns_not_found(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson(
            $course,
            [
                'published_at' => now()->addDay(),
            ]
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress"
            );

        $response->assertNotFound();
    }

    public function test_lesson_from_another_course_returns_not_found(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $otherCourse = $this->createCourse();

        $lesson = $this->createLesson(
            $otherCourse
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress"
            );

        $response->assertNotFound();
    }

    public function test_nonexistent_lesson_returns_not_found(): void
    {
        $user = User::factory()->create();

        $course = $this->createCourse();

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/" .
                '00000000-0000-0000-0000-000000000000' .
                '/progress'
            );

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | User Isolation
    |--------------------------------------------------------------------------
    */

    public function test_one_user_cannot_update_another_users_progress(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        $course = $this->createCourse();

        $lesson = $this->createLesson($course);

        $otherProgress = CourseLessonProgress::factory()->create([
            'user_id' => $otherUser->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'position_seconds' => 50,
        ]);

        $this
            ->actingAs($user)
            ->putJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}/progress",
                [
                    'position_seconds' => 500,
                ]
            )
            ->assertOk();

        $otherProgress->refresh();

        $this->assertSame(
            50,
            $otherProgress->position_seconds
        );
    }
}