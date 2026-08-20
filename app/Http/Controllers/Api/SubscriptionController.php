<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SubscriptionController extends Controller
{
    /**
     * Subscription service.
     */
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {
    }

    /**
     * Get the authenticated customer's current subscription.
     */
    public function current(
        Request $request
    ): JsonResponse {
        $subscription = $request->user()
            ->subscriptions()
            ->with('membershipPlan')
            ->whereIn('status', [
                Subscription::STATUS_PENDING,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_PAST_DUE,
            ])
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $subscription,
        ]);
    }

    /**
     * Create a subscription.
     *
     * Free plans are activated immediately.
     * Paid plans remain pending until payment
     * is successfully completed.
     */
    public function store(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'membership_plan' => [
                'required',
                'string',
            ],
        ]);

        $plan = MembershipPlan::query()
            ->where('uuid', $validated['membership_plan'])
            ->first();

        if (! $plan) {
            return response()->json([
                'success' => false,
                'message' => 'Membership plan not found.',
            ], 404);
        }

        try {
            $subscription = $this->subscriptionService->create(
                $request->user(),
                $plan
            );

            $subscription->load('membershipPlan');

            return response()->json([
                'success' => true,
                'message' => $subscription->isPending()
                    ? 'Subscription created and is awaiting payment.'
                    : 'Subscription created successfully.',
                'data' => $subscription,
            ], 201);

        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel the authenticated customer's subscription.
     */
    public function cancel(
        Request $request,
        Subscription $subscription
    ): JsonResponse {
        try {
            $subscription = $this->subscriptionService->cancel(
                $subscription,
                $request->user()
            );

            $subscription->load('membershipPlan');

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully.',
                'data' => $subscription,
            ]);

        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}