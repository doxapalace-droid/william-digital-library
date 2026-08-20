<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A membership plan can be created.
     */
    public function test_membership_plan_can_be_created(): void
    {
        $plan = MembershipPlan::factory()->create([
            'name' => 'Doxa Monthly',
            'slug' => 'doxa-monthly',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_interval' => MembershipPlan::INTERVAL_MONTH,
            'billing_interval_count' => 1,
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertDatabaseHas('membership_plans', [
            'id' => $plan->id,
            'name' => 'Doxa Monthly',
            'slug' => 'doxa-monthly',
        ]);
    }

    /**
     * A membership plan has a UUID.
     */
    public function test_membership_plan_has_uuid(): void
    {
        $plan = MembershipPlan::factory()->create();

        $this->assertNotNull($plan->uuid);
    }

    /**
     * Membership plans use UUID for route binding.
     */
    public function test_membership_plan_uses_uuid_for_route_binding(): void
    {
        $plan = MembershipPlan::factory()->create();

        $this->assertSame(
            'uuid',
            $plan->getRouteKeyName()
        );
    }

    /**
     * Price is cast correctly.
     */
    public function test_membership_plan_price_is_cast_correctly(): void
    {
        $plan = MembershipPlan::factory()->create([
            'price' => 49.95,
        ]);

        $this->assertSame(
            '49.95',
            (string) $plan->price
        );
    }

    /**
     * Published at is cast to a datetime.
     */
    public function test_membership_plan_published_at_is_cast_correctly(): void
    {
        $plan = MembershipPlan::factory()->create([
            'published_at' => now()->subDay(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $plan->published_at
        );
    }

    /**
     * An active published plan is active.
     */
    public function test_active_published_plan_is_active(): void
    {
        $plan = MembershipPlan::factory()->create([
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertTrue($plan->isActive());
    }

    /**
     * A draft plan is not active.
     */
    public function test_draft_plan_is_not_active(): void
    {
        $plan = MembershipPlan::factory()->create([
            'is_active' => true,
            'is_published' => false,
        ]);

        $this->assertFalse($plan->isActive());
    }

    /**
     * An inactive plan is not active.
     */
    public function test_inactive_plan_is_not_active(): void
    {
        $plan = MembershipPlan::factory()->create([
            'is_active' => false,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertFalse($plan->isActive());
    }

    /**
     * A future published plan is not active.
     */
    public function test_future_published_plan_is_not_active(): void
    {
        $plan = MembershipPlan::factory()->create([
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->assertFalse($plan->isActive());
    }

    /**
     * Monthly plans are identified correctly.
     */
    public function test_monthly_plan_is_identified_correctly(): void
    {
        $plan = MembershipPlan::factory()->create([
            'billing_interval' => MembershipPlan::INTERVAL_MONTH,
        ]);

        $this->assertTrue($plan->isMonthly());
        $this->assertFalse($plan->isYearly());
    }

    /**
     * Yearly plans are identified correctly.
     */
    public function test_yearly_plan_is_identified_correctly(): void
    {
        $plan = MembershipPlan::factory()->create([
            'billing_interval' => MembershipPlan::INTERVAL_YEAR,
        ]);

        $this->assertTrue($plan->isYearly());
        $this->assertFalse($plan->isMonthly());
    }

    /**
     * A zero-price plan is free.
     */
    public function test_free_plan_is_identified_correctly(): void
    {
        $plan = MembershipPlan::factory()->create([
            'price' => 0,
        ]);

        $this->assertTrue($plan->isFree());
    }

    /**
     * A paid plan is not free.
     */
    public function test_paid_plan_is_not_free(): void
    {
        $plan = MembershipPlan::factory()->create([
            'price' => 25,
        ]);

        $this->assertFalse($plan->isFree());
    }

    /**
     * A plan with trial days has a trial.
     */
    public function test_plan_with_trial_has_trial(): void
    {
        $plan = MembershipPlan::factory()->create([
            'trial_days' => 14,
        ]);

        $this->assertTrue($plan->hasTrial());
    }

    /**
     * A plan without trial days has no trial.
     */
    public function test_plan_without_trial_has_no_trial(): void
    {
        $plan = MembershipPlan::factory()->create([
            'trial_days' => null,
        ]);

        $this->assertFalse($plan->hasTrial());
    }

    /**
     * Plan price is formatted correctly.
     */
    public function test_membership_plan_price_is_formatted_correctly(): void
    {
        $plan = MembershipPlan::factory()->create([
            'price' => 29.99,
            'currency' => 'USD',
        ]);

        $this->assertSame(
            'USD 29.99',
            $plan->formattedPrice()
        );
    }

    /**
     * A membership plan has many subscriptions.
     */
    public function test_membership_plan_has_many_subscriptions(): void
    {
        $plan = MembershipPlan::factory()->create();

        $user = \App\Models\User::factory()->create();

        \App\Models\Subscription::factory()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
        ]);

        $this->assertCount(
            1,
            $plan->subscriptions
        );
    }
}