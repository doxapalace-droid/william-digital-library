<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A subscription can be created.
     */
    public function test_subscription_can_be_created(): void
    {
        $user = User::factory()->create();
        $plan = MembershipPlan::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => $plan->price,
            'currency' => $plan->currency,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
        ]);
    }

    /**
     * A subscription has a UUID.
     */
    public function test_subscription_has_uuid(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertNotNull($subscription->uuid);
    }

    /**
     * Subscription uses UUID for route binding.
     */
    public function test_subscription_uses_uuid_for_route_binding(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertSame(
            'uuid',
            $subscription->getRouteKeyName()
        );
    }

    /**
     * Subscription belongs to a user.
     */
    public function test_subscription_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertTrue(
            $subscription->user->is($user)
        );
    }

    /**
     * Subscription belongs to a membership plan.
     */
    public function test_subscription_belongs_to_membership_plan(): void
    {
        $plan = MembershipPlan::factory()->create();

        $subscription = Subscription::factory()->create([
            'membership_plan_id' => $plan->id,
        ]);

        $this->assertTrue(
            $subscription->membershipPlan->is($plan)
        );
    }

    /**
     * Active subscription is active.
     */
    public function test_active_subscription_is_active(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);

        $this->assertTrue(
            $subscription->isActive()
        );
    }

    /**
     * Expired active subscription is not active.
     */
    public function test_expired_active_subscription_is_not_active(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse(
            $subscription->isActive()
        );
    }

    /**
     * Trialing subscription is identified correctly.
     */
    public function test_trialing_subscription_is_identified_correctly(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->assertTrue(
            $subscription->isTrialing()
        );
    }

    /**
     * Expired trial is no longer trialing.
     */
    public function test_expired_trial_is_not_trialing(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse(
            $subscription->isTrialing()
        );
    }

    /**
     * Cancelled subscription is identified correctly.
     */
    public function test_cancelled_subscription_is_identified_correctly(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_CANCELLED,
        ]);

        $this->assertTrue(
            $subscription->isCancelled()
        );
    }

    /**
     * Expired subscription is identified correctly.
     */
    public function test_expired_subscription_is_identified_correctly(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue(
            $subscription->isExpired()
        );
    }

    /**
     * An active subscription can be cancelled.
     */
    public function test_active_subscription_can_be_cancelled(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertTrue(
            $subscription->canBeCancelled()
        );
    }

    /**
     * A cancelled subscription cannot be cancelled again.
     */
    public function test_cancelled_subscription_cannot_be_cancelled(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_CANCELLED,
        ]);

        $this->assertFalse(
            $subscription->canBeCancelled()
        );
    }

    /**
     * Active subscription grants access.
     */
    public function test_active_subscription_grants_access(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);

        $this->assertTrue(
            $subscription->grantsAccess()
        );
    }

    /**
     * Trialing subscription grants access.
     */
    public function test_trialing_subscription_grants_access(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(14),
            'expires_at' => null,
        ]);

        $this->assertTrue(
            $subscription->grantsAccess()
        );
    }

    /**
     * Past-due subscription continues to grant access
     * until it expires.
     */
    public function test_past_due_subscription_grants_access_until_expiration(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_PAST_DUE,
            'expires_at' => now()->addDays(3),
        ]);

        $this->assertTrue(
            $subscription->grantsAccess()
        );
    }

    /**
     * Expired subscription does not grant access.
     */
    public function test_expired_subscription_does_not_grant_access(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse(
            $subscription->grantsAccess()
        );
    }

    /**
     * Cancelled subscription does not grant access.
     */
    public function test_cancelled_subscription_does_not_grant_access(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_CANCELLED,
            'expires_at' => null,
        ]);

        $this->assertFalse(
            $subscription->grantsAccess()
        );
    }

    /**
     * Subscription amount is cast correctly.
     */
    public function test_subscription_amount_is_cast_correctly(): void
    {
        $subscription = Subscription::factory()->create([
            'amount' => 49.95,
        ]);

        $this->assertSame(
            '49.95',
            (string) $subscription->amount
        );
    }

    /**
     * Subscription timestamps are cast correctly.
     */
    public function test_subscription_timestamps_are_cast_correctly(): void
    {
        $subscription = Subscription::factory()->create([
            'starts_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'next_billing_at' => now()->addMonth(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $subscription->starts_at
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $subscription->current_period_start
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $subscription->current_period_end
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $subscription->next_billing_at
        );
    }

    /**
     * A user has many subscriptions.
     */
    public function test_user_has_many_subscriptions(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->count(2)->create([
            'user_id' => $user->id,
        ]);

        $this->assertCount(
            2,
            $user->subscriptions
        );
    }

    /**
     * User can determine whether they have an active
     * membership subscription.
     */
    public function test_user_can_determine_active_membership(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);

        $this->assertTrue(
            $user->hasActiveSubscription()
        );
    }

    /**
     * User does not have active membership when the
     * subscription has expired.
     */
    public function test_user_does_not_have_active_membership_when_subscription_expired(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse(
            $user->hasActiveSubscription()
        );
    }
}