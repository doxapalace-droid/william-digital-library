<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
    }

    public function test_free_plan_can_be_created_immediately(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 0,
            'is_active' => true,
            'is_published' => true,
            'billing_interval' => MembershipPlan::INTERVAL_MONTH,
            'billing_interval_count' => 1,
            'trial_days' => null,
        ]);

        $subscription = $this->service->create(
            $user,
            $plan
        );

        $this->assertSame(
            Subscription::STATUS_ACTIVE,
            $subscription->status
        );

        $this->assertSame(
            0.0,
            (float) $subscription->amount
        );

        $this->assertNotNull(
            $subscription->starts_at
        );

        $this->assertNotNull(
            $subscription->current_period_start
        );

        $this->assertNotNull(
            $subscription->current_period_end
        );

        $this->assertNotNull(
            $subscription->next_billing_at
        );
    }

    public function test_free_plan_with_trial_is_created_as_trialing(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 0,
            'is_active' => true,
            'is_published' => true,
            'trial_days' => 7,
            'billing_interval' => MembershipPlan::INTERVAL_MONTH,
            'billing_interval_count' => 1,
        ]);

        $subscription = $this->service->create(
            $user,
            $plan
        );

        $this->assertSame(
            Subscription::STATUS_TRIALING,
            $subscription->status
        );

        $this->assertNotNull(
            $subscription->trial_ends_at
        );

        $this->assertTrue(
            $subscription->trial_ends_at->isFuture()
        );
    }

    public function test_paid_plan_without_trial_is_pending(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 50,
            'is_active' => true,
            'is_published' => true,
            'trial_days' => null,
            'billing_interval' => MembershipPlan::INTERVAL_MONTH,
            'billing_interval_count' => 1,
        ]);

        $subscription = $this->service->create(
            $user,
            $plan
        );

        $this->assertSame(
            Subscription::STATUS_PENDING,
            $subscription->status
        );

        $this->assertNull(
            $subscription->starts_at
        );

        $this->assertNull(
            $subscription->current_period_start
        );

        $this->assertNull(
            $subscription->current_period_end
        );

        $this->assertNull(
            $subscription->next_billing_at
        );
    }

    public function test_paid_plan_with_trial_starts_as_trialing(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 50,
            'is_active' => true,
            'is_published' => true,
            'trial_days' => 14,
            'billing_interval' => MembershipPlan::INTERVAL_MONTH,
            'billing_interval_count' => 1,
        ]);

        $subscription = $this->service->create(
            $user,
            $plan
        );

        $this->assertSame(
            Subscription::STATUS_TRIALING,
            $subscription->status
        );

        $this->assertNotNull(
            $subscription->trial_ends_at
        );

        $this->assertTrue(
            $subscription->trial_ends_at->isFuture()
        );
    }

    public function test_unavailable_plan_cannot_create_subscription(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 20,
            'is_active' => false,
            'is_published' => true,
        ]);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'This membership plan is not currently available.'
        );

        $this->service->create(
            $user,
            $plan
        );
    }

    public function test_customer_with_active_subscription_cannot_create_another(): void
    {
        $user = User::factory()->create();

        $existingPlan = MembershipPlan::factory()->create([
            'price' => 20,
            'is_active' => true,
            'is_published' => true,
        ]);

        $newPlan = MembershipPlan::factory()->create([
            'price' => 30,
            'is_active' => true,
            'is_published' => true,
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $existingPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'You already have an active membership subscription.'
        );

        $this->service->create(
            $user,
            $newPlan
        );
    }

    public function test_pending_paid_subscription_can_be_activated(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 50,
            'is_active' => true,
            'is_published' => true,
            'trial_days' => null,
            'billing_interval' => MembershipPlan::INTERVAL_MONTH,
            'billing_interval_count' => 1,
        ]);

        $subscription = $this->service->create(
            $user,
            $plan
        );

        $this->assertSame(
            Subscription::STATUS_PENDING,
            $subscription->status
        );

        $subscription = $this->service->activate(
            $subscription,
            'PAYSTACK-TEST-REFERENCE'
        );

        $this->assertSame(
            Subscription::STATUS_ACTIVE,
            $subscription->status
        );

        $this->assertSame(
            'PAYSTACK-TEST-REFERENCE',
            $subscription->payment_reference
        );

        $this->assertNotNull(
            $subscription->starts_at
        );

        $this->assertNotNull(
            $subscription->current_period_start
        );

        $this->assertNotNull(
            $subscription->current_period_end
        );

        $this->assertNotNull(
            $subscription->next_billing_at
        );
    }

    public function test_pending_paid_subscription_with_trial_becomes_trialing_when_activated(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 50,
            'is_active' => true,
            'is_published' => true,
            'trial_days' => 7,
            'billing_interval' => MembershipPlan::INTERVAL_MONTH,
            'billing_interval_count' => 1,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => Subscription::STATUS_PENDING,
            'amount' => $plan->price,
            'currency' => $plan->currency,
        ]);

        $subscription = $this->service->activate(
            $subscription,
            'PAYSTACK-TRIAL-REFERENCE'
        );

        $this->assertSame(
            Subscription::STATUS_TRIALING,
            $subscription->status
        );

        $this->assertNotNull(
            $subscription->trial_ends_at
        );

        $this->assertTrue(
            $subscription->trial_ends_at->isFuture()
        );

        $this->assertSame(
            'PAYSTACK-TRIAL-REFERENCE',
            $subscription->payment_reference
        );
    }

    public function test_non_pending_subscription_cannot_be_activated(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 50,
            'is_active' => true,
            'is_published' => true,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Only pending subscriptions can be activated.'
        );

        $this->service->activate($subscription);
    }

    public function test_customer_can_cancel_their_subscription(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 50,
            'is_active' => true,
            'is_published' => true,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $subscription = $this->service->cancel(
            $subscription,
            $user
        );

        $this->assertSame(
            Subscription::STATUS_CANCELLED,
            $subscription->status
        );

        $this->assertNotNull(
            $subscription->cancelled_at
        );
    }

    public function test_customer_cannot_cancel_another_users_subscription(): void
    {
        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 50,
            'is_active' => true,
            'is_published' => true,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $owner->id,
            'membership_plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'You cannot cancel another user\'s subscription.'
        );

        $this->service->cancel(
            $subscription,
            $otherUser
        );
    }

    public function test_cancelled_subscription_cannot_be_cancelled_again(): void
    {
        $user = User::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'price' => 50,
            'is_active' => true,
            'is_published' => true,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'This subscription cannot be cancelled.'
        );

        $this->service->cancel(
            $subscription,
            $user
        );
    }
}