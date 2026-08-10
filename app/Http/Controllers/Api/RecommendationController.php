<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * Recommendation service instance.
     */
    public function __construct(
        protected RecommendationService $recommendationService
    ) {
    }

    /**
     * Return personalized recommendations.
     */
    public function index(Request $request): JsonResponse
    {
        $books = $this->recommendationService
            ->getRecommendations($request->user());

        return response()->json([
            'data' => $books,
        ]);
    }
}