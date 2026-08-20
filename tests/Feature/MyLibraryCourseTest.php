<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyLibraryCourseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authenticated users can view courses they own.
     */
    public function test_authenticated_user_can_view_owned_course(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $course->uuid,
                'title' => $course->title,
            ]);
    }

    /**
     * A user cannot see another user's course.
     */
    public function test_user_cannot_see_another_users_course(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $otherUser->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'uuid' => $course->uuid,
            ]);
    }

    /**
     * Expired course entitlements must not appear.
     */
    public function test_expired_course_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'revoked_at' => null,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'uuid' => $course->uuid,
            ]);
    }

    /**
     * Revoked course entitlements must not appear.
     */
    public function test_revoked_course_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'revoked_at' => now()->subHour(),
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'uuid' => $course->uuid,
            ]);
    }

    /**
     * Inactive course entitlements must not appear.
     */
    public function test_inactive_course_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'inactive',
            'can_access' => true,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'uuid' => $course->uuid,
            ]);
    }

    /**
     * Courses without access permission must not appear.
     */
    public function test_course_without_access_permission_does_not_appear(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => false,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'uuid' => $course->uuid,
            ]);
    }

    /**
     * Inactive courses must not appear in the library.
     */
    public function test_inactive_course_does_not_appear(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'inactive',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'uuid' => $course->uuid,
            ]);
    }

    /**
     * Future courses must not appear in the library.
     */
    public function test_future_course_does_not_appear(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'uuid' => $course->uuid,
            ]);
    }

    /**
     * Course information returned by My Library
     * should contain useful public catalogue information.
     */
    public function test_course_contains_library_information(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'title' => 'Kingdom Leadership',
            'slug' => 'kingdom-leadership',
            'subtitle' => 'Leading with Kingdom Principles',
            'description' => 'A leadership course.',
            'cover_image' => 'courses/leadership.jpg',
            'price' => 100,
            'currency' => 'GHS',
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonPath(
                'courses.0.uuid',
                $course->uuid
            )
            ->assertJsonPath(
                'courses.0.title',
                'Kingdom Leadership'
            )
            ->assertJsonPath(
                'courses.0.slug',
                'kingdom-leadership'
            )
            ->assertJsonPath(
                'courses.0.cover_image',
                'courses/leadership.jpg'
            )
            ->assertJsonPath(
                'courses.0.currency',
                'GHS'
            );
    }

    /**
     * Guests cannot access My Library.
     */
    public function test_guest_cannot_view_course_library(): void
    {
        $response = $this->getJson('/api/my-library');

        $response->assertUnauthorized();
    }
}