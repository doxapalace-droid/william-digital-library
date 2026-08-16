<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audiobook;
use App\Models\AudiobookChapter;
use App\Models\AudiobookListeningProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AudiobookListeningProgressController extends Controller
{
    /**
     * Display the authenticated customer's listening progress
     * for an audiobook.
     */
    public function show(
        Request $request,
        Audiobook $audiobook
    ): JsonResponse {
        $this->ensureAudiobookIsAccessible(
            $request,
            $audiobook
        );

        $progress = AudiobookListeningProgress::query()
            ->where('user_id', $request->user()->id)
            ->where('audiobook_id', $audiobook->id)
            ->with([
                'chapter',
            ])
            ->first();

        return response()->json([
            'data' => $progress,
        ]);
    }

    /**
     * Create or update listening progress for an audiobook.
     */
    public function update(
        Request $request,
        Audiobook $audiobook
    ): JsonResponse {
        $this->ensureAudiobookIsAccessible(
            $request,
            $audiobook
        );

        $validated = $request->validate([
            'audiobook_chapter_id' => [
                'nullable',
                'integer',
                Rule::exists('audiobook_chapters', 'id')
                    ->where(function ($query) use ($audiobook) {
                        $query->where(
                            'audiobook_id',
                            $audiobook->id
                        );
                    }),
            ],

            'position_seconds' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'listened_seconds' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_completed' => [
                'nullable',
                'boolean',
            ],
        ]);

        $user = $request->user();

        $progress = AudiobookListeningProgress::query()
            ->where('user_id', $user->id)
            ->where('audiobook_id', $audiobook->id)
            ->first();

        /*
         * Use existing values when the client does not
         * send an optional field.
         */
        $positionSeconds = array_key_exists(
            'position_seconds',
            $validated
        )
            ? (int) $validated['position_seconds']
            : ($progress?->position_seconds ?? 0);

        $listenedSeconds = array_key_exists(
            'listened_seconds',
            $validated
        )
            ? (int) $validated['listened_seconds']
            : ($progress?->listened_seconds ?? 0);

        /*
         * Do not allow the playback position to exceed
         * the current chapter duration.
         */
        if (
            isset($validated['audiobook_chapter_id'])
            && $validated['audiobook_chapter_id'] !== null
        ) {
            $chapter = AudiobookChapter::query()
                ->whereKey($validated['audiobook_chapter_id'])
                ->where('audiobook_id', $audiobook->id)
                ->first();

            if (
                $chapter !== null
                && $chapter->duration_seconds > 0
            ) {
                $positionSeconds = min(
                    $positionSeconds,
                    $chapter->duration_seconds
                );
            }
        }

        /*
         * Calculate overall audiobook progress from the
         * total listened seconds and the audiobook duration.
         */
        $durationSeconds = (int) $audiobook->duration_seconds;

        $progressPercent = 0;

        if ($durationSeconds > 0) {
            $progressPercent = min(
                100,
                round(
                    ($listenedSeconds / $durationSeconds) * 100,
                    2
                )
            );
        }

        /*
         * Completion may be explicitly supplied by the player,
         * or automatically detected when listened seconds reach
         * the audiobook duration.
         */
        $isCompleted = array_key_exists(
            'is_completed',
            $validated
        )
            ? (bool) $validated['is_completed']
            : (
                $durationSeconds > 0
                && $listenedSeconds >= $durationSeconds
            );

        /*
         * If the client explicitly marks the audiobook as
         * completed, make the progress percentage 100%.
         */
        if ($isCompleted) {
            $progressPercent = 100;
        }

        $data = [
            'position_seconds' => $positionSeconds,
            'listened_seconds' => $listenedSeconds,
            'progress_percent' => $progressPercent,
            'is_completed' => $isCompleted,
            'last_listened_at' => now(),
        ];

        /*
         * Only replace the chapter when the client explicitly
         * provides it.
         */
        if (array_key_exists(
            'audiobook_chapter_id',
            $validated
        )) {
            $data['audiobook_chapter_id'] =
                $validated['audiobook_chapter_id'];
        }

        $progress = AudiobookListeningProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
            ],
            $data
        );

        $progress->load([
            'chapter',
        ]);

        return response()->json([
            'message' => 'Audiobook listening progress saved successfully.',
            'data' => $progress,
        ]);
    }

    /**
     * Ensure the authenticated customer is entitled to
     * access the audiobook.
     */
    private function ensureAudiobookIsAccessible(
        Request $request,
        Audiobook $audiobook
    ): void {
        if (! $audiobook->isActive()) {
            abort(
                404,
                'Audiobook is not available.'
            );
        }

        $entitlement = $audiobook
            ->entitlements()
            ->where('user_id', $request->user()->id)
            ->first();

        if (
            $entitlement === null
            || ! $entitlement->canStream()
        ) {
            abort(
                403,
                'You do not have permission to access this audiobook.'
            );
        }
    }
}