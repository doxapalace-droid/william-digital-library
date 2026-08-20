<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\JsonResponse;

class MembershipPlanController extends Controller
{
    /**
     * Display all published membership plans.
     */
    public function index(): JsonResponse
    {
        $plans = MembershipPlan::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->where(function ($query) {
                $query
                    ->whereNull('published_at')
                    ->orWhere(
                        'published_at',
                        '<=',
                        now()
                    );
            })
            ->orderBy('price')
            ->orderBy('billing_interval')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Display a single published membership plan.
     */
    public function show(
        MembershipPlan $membershipPlan
    ): JsonResponse {
        if (! $membershipPlan->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'This membership plan is not currently available.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $membershipPlan,
        ]);
    }
}