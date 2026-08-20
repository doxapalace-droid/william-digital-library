<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Course;
use App\Services\FreeProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FreeProductController extends Controller
{
    /**
     * The free product service.
     */
    public function __construct(
        private readonly FreeProductService $freeProductService
    ) {
    }

    /**
     * Claim a free book.
     */
    public function claimBook(
        Request $request,
        Book $book
    ): JsonResponse {
        try {
            $entitlement = $this->freeProductService->claimBook(
                $request->user(),
                $book
            );

            return response()->json([
                'message' => 'Free book claimed successfully.',
                'data' => [
                    'entitlement' => $entitlement,
                    'product' => $book,
                ],
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * Claim a free audiobook.
     */
    public function claimAudiobook(
        Request $request,
        Audiobook $audiobook
    ): JsonResponse {
        try {
            $entitlement = $this->freeProductService->claimAudiobook(
                $request->user(),
                $audiobook
            );

            return response()->json([
                'message' => 'Free audiobook claimed successfully.',
                'data' => [
                    'entitlement' => $entitlement,
                    'product' => $audiobook,
                ],
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * Claim a free course.
     */
    public function claimCourse(
        Request $request,
        Course $course
    ): JsonResponse {
        try {
            $entitlement = $this->freeProductService->claimCourse(
                $request->user(),
                $course
            );

            return response()->json([
                'message' => 'Free course claimed successfully.',
                'data' => [
                    'entitlement' => $entitlement,
                    'product' => $course,
                ],
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}