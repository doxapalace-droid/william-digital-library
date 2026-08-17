<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Course can access its entitlements.
     */
    public function test_course_can_access_its_entitlements(): void
    {
        $course = Course::factory()->create();

        $entitlement = CourseEntitlement::factory()->create([
            'course_id' => $course->id,
        ]);

        $this->assertTrue(
            $course->entitlements
                ->contains($entitlement)
        );
    }

    /**
     * User can access their course entitlements.
     */
    public function test_user_can_access_course_entitlements(): void
    {
        $user = User::factory()->create();

        $entitlement = CourseEntitlement::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertTrue(
            $user->courseEntitlements
                ->contains($entitlement)
        );
    }

    /**
     * User can access a course with an active entitlement.
     */
    public function test_user_can_access_course_with_active_entitlement(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $this->assertTrue(
            $user->canAccessCourse($course)
        );
    }

    /**
     * User cannot access a course without an entitlement.
     */
    public function test_user_cannot_access_course_without_entitlement(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        $this->assertFalse(
            $user->canAccessCourse($course)
        );
    }

    /**
     * Expired entitlement denies course access.
     */
    public function test_expired_entitlement_denies_course_access(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()
            ->expired()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

        $this->assertFalse(
            $user->canAccessCourse($course)
        );
    }

    /**
     * Revoked entitlement denies course access.
     */
    public function test_revoked_entitlement_denies_course_access(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()
            ->revoked()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

        $this->assertFalse(
            $user->canAccessCourse($course)
        );
    }

    /**
     * Inactive entitlement denies course access.
     */
    public function test_inactive_entitlement_denies_course_access(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()
            ->inactive()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

        $this->assertFalse(
            $user->canAccessCourse($course)
        );
    }

    /**
     * An entitlement without access permission denies
     * course access.
     */
    public function test_without_access_permission_denies_course_access(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()
            ->withoutAccess()
            ->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

        $this->assertFalse(
            $user->canAccessCourse($course)
        );
    }

    /**
     * A future expiration date still allows access.
     */
    public function test_future_expiration_allows_course_access(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'expires_at' => now()->addMonth(),
            'revoked_at' => null,
        ]);

        $this->assertTrue(
            $user->canAccessCourse($course)
        );
    }

    /**
     * One user's entitlement does not grant another user access.
     */
    public function test_one_users_entitlement_does_not_grant_another_user_access(): void
    {
        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $owner->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $this->assertTrue(
            $owner->canAccessCourse($course)
        );

        $this->assertFalse(
            $otherUser->canAccessCourse($course)
        );
    }

    /**
     * Access is denied when the entitlement belongs to
     * a different course.
     */
    public function test_entitlement_for_different_course_does_not_grant_access(): void
    {
        $user = User::factory()->create();

        $ownedCourse = Course::factory()->create();

        $otherCourse = Course::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $otherCourse->id,
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $this->assertFalse(
            $user->canAccessCourse($ownedCourse)
        );

        $this->assertTrue(
            $user->canAccessCourse($otherCourse)
        );
    }

    /**
     * A revoked entitlement denies access even when
     * its expiration date is in the future.
     */
    public function test_revoked_entitlement_overrides_future_expiration(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => true,
            'expires_at' => now()->addMonth(),
            'revoked_at' => now(),
        ]);

        $this->assertFalse(
            $user->canAccessCourse($course)
        );
    }

    /**
     * An inactive entitlement denies access even when
     * its expiration date is in the future.
     */
    public function test_inactive_entitlement_overrides_future_expiration(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'inactive',
            'can_access' => true,
            'expires_at' => now()->addMonth(),
            'revoked_at' => null,
        ]);

        $this->assertFalse(
            $user->canAccessCourse($course)
        );
    }

    /**
     * A non-access entitlement denies access even when
     * the entitlement itself is active.
     */
    public function test_active_entitlement_without_access_denies_course_access(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'can_access' => false,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $this->assertFalse(
            $user->canAccessCourse($course)
        );
    }

    /**
     * Multiple courses can be accessed independently.
     */
    public function test_course_access_is_independent_between_courses(): void
    {
        $user = User::factory()->create();

        $courseOne = Course::factory()->create();

        $courseTwo = Course::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $courseOne->id,
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $courseTwo->id,
            'status' => 'inactive',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $this->assertTrue(
            $user->canAccessCourse($courseOne)
        );

        $this->assertFalse(
            $user->canAccessCourse($courseTwo)
        );
    }
}