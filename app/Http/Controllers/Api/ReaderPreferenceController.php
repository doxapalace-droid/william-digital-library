<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReaderPreferenceRequest;
use App\Http\Requests\UpdateReaderPreferenceRequest;
use App\Http\Resources\ReaderPreferenceResource;
use App\Models\ReaderPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReaderPreferenceController extends Controller
{
    /**
     * Display the authenticated user's reader preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $preference = ReaderPreference::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'data' => $preference
                ? new ReaderPreferenceResource($preference)
                : null,
        ]);
    }

    /**
     * Create the authenticated user's reader preferences.
     */
    public function store(
        StoreReaderPreferenceRequest $request
    ): JsonResponse {
        $preference = ReaderPreference::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($preference) {
            return response()->json([
                'message' => 'Reader preferences already exist.',
                'data' => new ReaderPreferenceResource($preference),
            ], Response::HTTP_CONFLICT);
        }

        $preference = ReaderPreference::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'message' => 'Reader preferences created successfully.',
            'data' => new ReaderPreferenceResource($preference),
        ], Response::HTTP_CREATED);
    }

    /**
     * Create or update the authenticated user's reader preferences.
     *
     * PUT is treated as an upsert:
     * - Creates the preferences if they do not exist.
     * - Updates the existing preferences if they already exist.
     */
    public function update(
        UpdateReaderPreferenceRequest $request
    ): JsonResponse {
        $preference = ReaderPreference::updateOrCreate(
            [
                'user_id' => $request->user()->id,
            ],
            $request->validated()
        );

        return response()->json([
            'message' => 'Reader preferences saved successfully.',
            'data' => new ReaderPreferenceResource(
                $preference->fresh()
            ),
        ]);
    }
}