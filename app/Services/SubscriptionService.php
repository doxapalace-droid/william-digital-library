<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubscriptionService
{
    /**
     * Create a subscription for a customer.
     *
     * Free plans are activated immediately.
     * Paid plans are created as pending until payment
     * has been successfully completed.
     */
    public function create(
        User $user,
        MembershipPlan $plan,
        string $gateway = 'paystack'
    ): Subscription {
        /*
         * The membership plan must be available
         * for purchase.
         */
        if (! $plan->isPurchasable()) {
            throw new RuntimeException(
                'This membership plan is not currently available.'
            );
        }

        /*
         * Prevent a customer from creating multiple
         * active memberships unnecessarily.
         */
        if ($user->hasActiveSubscription()) {
            throw new RuntimeException(
                'You already have an active membership subscription.'
            );
        }

        /*
         * Create the subscription atomically.
         */
        return DB::transaction(function () use (
            $user,
            $plan,
            $gateway
        ) {
            $now = now();

            /*
             * Determine whether the plan has a trial.
             */
            $trialEndsAt = null;

            if ($plan->hasTrial()) {
                $trialEndsAt = $now->copy()
                    ->addDays($plan->trial_days);
            }

            /*
             * Free plans can become active immediately.
             *
             * Paid plans remain pending until payment
             * has been verified.
             */
            $status = $plan->isFree()
                ? (
                    $plan->hasTrial()
                        ? Subscription::STATUS_TRIALING
                        : Subscription::STATUS_ACTIVE
                )
                : (
                    $plan->hasTrial()
                        ? Subscription::STATUS_TRIALING
                        : Subscription::STATUS_PENDING
                );

            /*
             * Calculate the first subscription period.
             *
             * The billing interval belongs to the plan,
             * while the subscription stores the actual
             * dates for the customer's current period.
             */
            $periodStart = $now->copy();

            $periodEnd = $this->calculatePeriodEnd(
                $periodStart,
                $plan
            );

            /*
             * For a trial, the actual billing period begins
             * after the trial ends.
             */
            if ($trialEndsAt !== null) {
                $periodStart = $trialEndsAt->copy();

                $periodEnd = $this->calculatePeriodEnd(
                    $periodStart,
                    $plan
                );
            }

            /*
             * Free subscriptions are immediately active
             * and therefore receive a billing schedule.
             *
             * Paid subscriptions without a trial remain
             * pending until payment verification.
             */
            $currentPeriodStart = null;
            $currentPeriodEnd = null;
            $nextBillingAt = null;
            $startsAt = null;

            if (
                $status === Subscription::STATUS_ACTIVE
                || $status === Subscription::STATUS_TRIALING
            ) {
                $startsAt = $now;
                $currentPeriodStart = $periodStart;
                $currentPeriodEnd = $periodEnd;
                $nextBillingAt = $periodEnd;
            }

            return Subscription::create([
                'user_id' => $user->id,
                'membership_plan_id' => $plan->id,

                'status' => $status,

                /*
                 * Capture the plan price at subscription
                 * creation time.
                 */
                'amount' => $plan->price,
                'currency' => strtoupper($plan->currency),

                'starts_at' => $startsAt,

                'trial_ends_at' => $trialEndsAt,

                'current_period_start' =>
                    $currentPeriodStart,

                'current_period_end' =>
                    $currentPeriodEnd,

                'next_billing_at' =>
                    $nextBillingAt,

                'cancelled_at' => null,

                'expires_at' =>
                    $currentPeriodEnd,

                'gateway' =>
                    $plan->isFree()
                        ? null
                        : $gateway,

                'payment_reference' => null,
            ]);
        });
    }

    /**
     * Activate a pending paid subscription after
     * successful payment verification.
     */
    public function activate(
        Subscription $subscription,
        ?string $paymentReference = null
    ): Subscription {
        if (
            $subscription->status !==
            Subscription::STATUS_PENDING
        ) {
            throw new RuntimeException(
                'Only pending subscriptions can be activated.'
            );
        }

        $plan = $subscription->membershipPlan;

        if (! $plan) {
            throw new RuntimeException(
                'The membership plan could not be found.'
            );
        }

        return DB::transaction(function () use (
            $subscription,
            $plan,
            $paymentReference
        ) {
            $now = now();

            /*
             * Determine whether the subscription starts
             * with a trial.
             */
            $trialEndsAt = null;

            if ($plan->hasTrial()) {
                $trialEndsAt = $now->copy()
                    ->addDays($plan->trial_days);
            }

            /*
             * The current billing period begins now
             * or after the trial.
             */
            $periodStart = $trialEndsAt
                ? $trialEndsAt->copy()
                : $now->copy();

            $periodEnd = $this->calculatePeriodEnd(
                $periodStart,
                $plan
            );

            $status = $trialEndsAt
                ? Subscription::STATUS_TRIALING
                : Subscription::STATUS_ACTIVE;

            $subscription->update([
                'status' => $status,

                'starts_at' => $now,

                'trial_ends_at' => $trialEndsAt,

                'current_period_start' =>
                    $periodStart,

                'current_period_end' =>
                    $periodEnd,

                'next_billing_at' =>
                    $periodEnd,

                'expires_at' =>
                    $periodEnd,

                'payment_reference' =>
                    $paymentReference
                    ?? $subscription->payment_reference,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Cancel a customer's subscription.
     */
    public function cancel(
        Subscription $subscription,
        User $user
    ): Subscription {
        /*
         * Make sure the subscription belongs
         * to the requesting customer.
         */
        if (
            (int) $subscription->user_id !==
            (int) $user->id
        ) {
            throw new RuntimeException(
                'You cannot cancel another user\'s subscription.'
            );
        }

        if (! $subscription->canBeCancelled()) {
            throw new RuntimeException(
                'This subscription cannot be cancelled.'
            );
        }

        $subscription->cancel();

        return $subscription->fresh();
    }

    /**
     * Calculate the end of a billing period.
     */
    protected function calculatePeriodEnd(
        \DateTimeInterface $start,
        MembershipPlan $plan
    ): \DateTimeInterface {
        $date = now()->setTimestamp(
            $start->getTimestamp()
        );

        if (
            $plan->billing_interval ===
            MembershipPlan::INTERVAL_YEAR
        ) {
            return $date->addYears(
                $plan->billing_interval_count
            );
        }

        return $date->addMonths(
            $plan->billing_interval_count
        );
    }
}