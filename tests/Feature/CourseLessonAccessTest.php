<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\CourseLesson;
use App\Models\Video;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLessonAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A preview lesson can be viewed by a guest.
     */
    public function test_guest_can_view_preview_lesson(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => true,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $lesson->uuid
            )
            ->assertJsonPath(
                'data.is_preview',
                true
            );
    }

    /**
     * An authenticated user can view a preview lesson.
     */
    public function test_authenticated_user_can_view_preview_lesson(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => true,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertOk();
    }

    /**
     * A user with an active entitlement can view
     * a non-preview lesson.
     */
    public function test_user_with_active_entitlement_can_view_paid_lesson(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $lesson->uuid
            );
    }

    /**
     * A guest cannot view a non-preview lesson.
     */
    public function test_guest_cannot_view_paid_lesson(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
        );

        $response->assertUnauthorized();
    }

    /**
     * A user without an entitlement cannot view
     * a non-preview lesson.
     */
    public function test_user_without_entitlement_cannot_view_paid_lesson(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertForbidden();
    }

    /**
     * An expired entitlement cannot access a paid lesson.
     */
    public function test_expired_entitlement_cannot_view_paid_lesson(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()
            ->expired()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertForbidden();
    }

    /**
     * A revoked entitlement cannot access a paid lesson.
     */
    public function test_revoked_entitlement_cannot_view_paid_lesson(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()
            ->revoked()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertForbidden();
    }

    /**
     * An inactive entitlement cannot access a paid lesson.
     */
    public function test_inactive_entitlement_cannot_view_paid_lesson(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()
            ->inactive()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertForbidden();
    }

    /**
     * An entitlement without access permission cannot
     * access a paid lesson.
     */
    public function test_entitlement_without_access_permission_cannot_view_paid_lesson(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()
            ->withoutAccess()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertForbidden();
    }

    /**
     * An entitlement belonging to another user does not
     * grant access.
     */
    public function test_another_users_entitlement_does_not_grant_access(): void
    {
        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $owner->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertForbidden();
    }

    /**
     * An inactive course cannot expose its lessons.
     */
    public function test_inactive_course_returns_not_found(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'inactive',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => true,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertNotFound();
    }

    /**
     * A future course cannot expose its lessons.
     */
    public function test_future_course_returns_not_found(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => true,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertNotFound();
    }

    /**
     * A draft lesson cannot be accessed.
     */
    public function test_draft_lesson_returns_not_found(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => true,
            'status' => 'draft',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * An inactive lesson cannot be accessed.
     */
    public function test_inactive_lesson_returns_not_found(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => true,
            'status' => 'inactive',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * A future lesson cannot be accessed.
     */
    public function test_future_lesson_returns_not_found(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => true,
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * A lesson belonging to another course cannot be
     * accessed through a different course URL.
     */
    public function test_lesson_from_another_course_returns_not_found(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $otherCourse = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $otherCourse->id,
            'is_preview' => true,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * A nonexistent lesson returns not found.
     */
    public function test_nonexistent_lesson_returns_not_found(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/" .
            '00000000-0000-0000-0000-000000000000'
        );

        $response->assertNotFound();
    }

    /**
     * A lesson can include its associated video.
     */
    public function test_lesson_response_includes_video_information(): void
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
            'is_preview' => true,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.video.uuid',
                $video->uuid
            )
            ->assertJsonPath(
                'data.video.title',
                $video->title
            );
    }

    /**
     * The private video file must never be exposed.
     */
    public function test_lesson_response_does_not_expose_private_video_file(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $video = Video::factory()->create([
            'video_file' => 'private/videos/course-video.mp4',
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'video_id' => $video->id,
            'is_preview' => true,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonMissing([
                'video_file' => 'private/videos/course-video.mp4',
            ]);
    }

    /**
     * A future entitlement still grants access while active.
     */
    public function test_future_expiring_entitlement_allows_paid_lesson(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'is_preview' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'expires_at' => now()->addMonth(),
            'revoked_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/courses/{$course->uuid}/lessons/{$lesson->uuid}"
            );

        $response->assertOk();
    }
}