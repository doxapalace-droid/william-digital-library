<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CourseEntitlementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An entitlement can be created.
     */
    public function test_entitlement_can_be_created(): void
    {
        $entitlement = CourseEntitlement::factory()->create();

        $this->assertDatabaseHas('course_entitlements', [
            'id' => $entitlement->id,
            'uuid' => $entitlement->uuid,
            'user_id' => $entitlement->user_id,
            'course_id' => $entitlement->course_id,
            'source' => 'purchase',
            'status' => 'active',
            'can_access' => true,
        ]);
    }

    /**
     * An entitlement has a UUID.
     */
    public function test_entitlement_has_uuid(): void
    {
        $entitlement = CourseEntitlement::factory()->create();

        $this->assertNotNull($entitlement->uuid);
        $this->assertIsString($entitlement->uuid);
    }

    /**
     * Entitlement uses UUID for route model binding.
     */
    public function test_entitlement_uses_uuid_for_route_binding(): void
    {
        $entitlement = CourseEntitlement::factory()->create();

        $this->assertSame(
            'uuid',
            $entitlement->getRouteKeyName()
        );
    }

    /**
     * An entitlement belongs to a user.
     */
    public function test_entitlement_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $entitlement = CourseEntitlement::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertTrue(
            $entitlement->user->is($user)
        );
    }

    /**
     * An entitlement belongs to a course.
     */
    public function test_entitlement_belongs_to_course(): void
    {
        $course = Course::factory()->create();

        $entitlement = CourseEntitlement::factory()->create([
            'course_id' => $course->id,
        ]);

        $this->assertTrue(
            $entitlement->course->is($course)
        );
    }

    /**
     * Boolean values are cast correctly.
     */
    public function test_can_access_is_cast_to_boolean(): void
    {
        $entitlement = CourseEntitlement::factory()->create([
            'can_access' => 1,
        ]);

        $this->assertTrue(
            $entitlement->can_access
        );

        $this->assertIsBool(
            $entitlement->can_access
        );
    }

    /**
     * Date values are cast correctly.
     */
    public function test_entitlement_dates_are_cast_to_datetime(): void
    {
        $entitlement = CourseEntitlement::factory()->create([
            'granted_at' => now(),
            'expires_at' => now()->addMonth(),
            'revoked_at' => null,
        ]);

        $this->assertInstanceOf(
            Carbon::class,
            $entitlement->granted_at
        );

        $this->assertInstanceOf(
            Carbon::class,
            $entitlement->expires_at
        );

        $this->assertNull(
            $entitlement->revoked_at
        );
    }

    /**
     * An active entitlement is active.
     */
    public function test_active_entitlement_is_active(): void
    {
        $entitlement = CourseEntitlement::factory()->create([
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $this->assertTrue(
            $entitlement->isActive()
        );
    }

    /**
     * An active entitlement allows course access.
     */
    public function test_active_entitlement_allows_access(): void
    {
        $entitlement = CourseEntitlement::factory()->create([
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $this->assertTrue(
            $entitlement->canAccess()
        );
    }

    /**
     * An expired entitlement is not active.
     */
    public function test_expired_entitlement_is_not_active(): void
    {
        $entitlement = CourseEntitlement::factory()
            ->expired()
            ->create();

        $this->assertFalse(
            $entitlement->isActive()
        );
    }

    /**
     * An expired entitlement does not allow access.
     */
    public function test_expired_entitlement_does_not_allow_access(): void
    {
        $entitlement = CourseEntitlement::factory()
            ->expired()
            ->create();

        $this->assertFalse(
            $entitlement->canAccess()
        );
    }

    /**
     * A revoked entitlement is not active.
     */
    public function test_revoked_entitlement_is_not_active(): void
    {
        $entitlement = CourseEntitlement::factory()
            ->revoked()
            ->create();

        $this->assertFalse(
            $entitlement->isActive()
        );
    }

    /**
     * A revoked entitlement does not allow access.
     */
    public function test_revoked_entitlement_does_not_allow_access(): void
    {
        $entitlement = CourseEntitlement::factory()
            ->revoked()
            ->create();

        $this->assertFalse(
            $entitlement->canAccess()
        );
    }

    /**
     * An inactive entitlement is not active.
     */
    public function test_inactive_entitlement_is_not_active(): void
    {
        $entitlement = CourseEntitlement::factory()
            ->inactive()
            ->create();

        $this->assertFalse(
            $entitlement->isActive()
        );
    }

    /**
     * An inactive entitlement does not allow access.
     */
    public function test_inactive_entitlement_does_not_allow_access(): void
    {
        $entitlement = CourseEntitlement::factory()
            ->inactive()
            ->create();

        $this->assertFalse(
            $entitlement->canAccess()
        );
    }

    /**
     * An entitlement without access permission does not
     * allow course access even when otherwise active.
     */
    public function test_entitlement_without_access_permission_does_not_allow_access(): void
    {
        $entitlement = CourseEntitlement::factory()
            ->withoutAccess()
            ->create();

        $this->assertTrue(
            $entitlement->isActive()
        );

        $this->assertFalse(
            $entitlement->canAccess()
        );
    }

    /**
     * A future expiration date does not prevent access.
     */
    public function test_future_expiration_does_not_prevent_access(): void
    {
        $entitlement = CourseEntitlement::factory()->create([
            'status' => 'active',
            'can_access' => true,
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ]);

        $this->assertTrue(
            $entitlement->isActive()
        );

        $this->assertTrue(
            $entitlement->canAccess()
        );
    }

    /**
     * An entitlement without an expiration date does not expire.
     */
    public function test_null_expiration_does_not_expire_entitlement(): void
    {
        $entitlement = CourseEntitlement::factory()->create([
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $this->assertTrue(
            $entitlement->isActive()
        );

        $this->assertTrue(
            $entitlement->canAccess()
        );
    }

    /**
     * A free entitlement is active and grants access.
     */
    public function test_free_entitlement_grants_access(): void
    {
        $entitlement = CourseEntitlement::factory()
            ->free()
            ->create();

        $this->assertSame(
            'free',
            $entitlement->source
        );

        $this->assertTrue(
            $entitlement->isActive()
        );

        $this->assertTrue(
            $entitlement->canAccess()
        );
    }

    /**
     * An admin-granted entitlement is active and grants access.
     */
    public function test_admin_granted_entitlement_grants_access(): void
    {
        $entitlement = CourseEntitlement::factory()
            ->adminGrant()
            ->create();

        $this->assertSame(
            'admin',
            $entitlement->source
        );

        $this->assertTrue(
            $entitlement->isActive()
        );

        $this->assertTrue(
            $entitlement->canAccess()
        );
    }

    /**
     * A revoked entitlement remains inactive even when
     * its expiration date is in the future.
     */
    public function test_revocation_overrides_future_expiration(): void
    {
        $entitlement = CourseEntitlement::factory()->create([
            'status' => 'active',
            'can_access' => true,
            'expires_at' => now()->addMonth(),
            'revoked_at' => now(),
        ]);

        $this->assertFalse(
            $entitlement->isActive()
        );

        $this->assertFalse(
            $entitlement->canAccess()
        );
    }

    /**
     * A non-active status prevents access even when
     * the entitlement has not expired or been revoked.
     */
    public function test_status_overrides_other_access_conditions(): void
    {
        $entitlement = CourseEntitlement::factory()->create([
            'status' => 'inactive',
            'can_access' => true,
            'expires_at' => now()->addMonth(),
            'revoked_at' => null,
        ]);

        $this->assertFalse(
            $entitlement->isActive()
        );

        $this->assertFalse(
            $entitlement->canAccess()
        );
    }

    /**
     * The same user cannot have two entitlement records
     * for the same course.
     */
    public function test_duplicate_course_entitlement_is_rejected(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    /**
     * The same user can have entitlements for different courses.
     */
    public function test_user_can_have_entitlements_for_different_courses(): void
    {
        $user = User::factory()->create();

        $courseOne = Course::factory()->create();
        $courseTwo = Course::factory()->create();

        $entitlementOne = CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $courseOne->id,
        ]);

        $entitlementTwo = CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $courseTwo->id,
        ]);

        $this->assertNotSame(
            $entitlementOne->course_id,
            $entitlementTwo->course_id
        );

        $this->assertDatabaseCount(
            'course_entitlements',
            2
        );
    }

    /**
     * Different users can have entitlements for the same course.
     */
    public function test_different_users_can_have_same_course_entitlement(): void
    {
        $course = Course::factory()->create();

        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        CourseEntitlement::factory()->create([
            'user_id' => $userOne->id,
            'course_id' => $course->id,
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $userTwo->id,
            'course_id' => $course->id,
        ]);

        $this->assertDatabaseCount(
            'course_entitlements',
            2
        );
    }
}