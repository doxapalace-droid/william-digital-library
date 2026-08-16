<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audiobook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AudiobookDownloadController extends Controller
{
    /**
     * Download an audiobook.
     *
     * Access requires:
     *
     * - authenticated customer
     * - active audiobook
     * - active entitlement
     * - download permission
     * - audiobook audio files existing
     */
    public function download(
        Request $request,
        Audiobook $audiobook
    ): StreamedResponse {
        $user = $request->user();

        /*
         * The audiobook must be publicly active.
         */
        if (! $audiobook->isActive()) {
            abort(
                404,
                'Audiobook is not available.'
            );
        }

        /*
         * The customer must have an active entitlement
         * that explicitly permits downloading.
         */
        $entitlement = $audiobook
            ->entitlements()
            ->where('user_id', $user->id)
            ->first();

        if (
            $entitlement === null
            || ! $entitlement->canDownload()
        ) {
            abort(
                403,
                'You do not have permission to download this audiobook.'
            );
        }

        /*
         * Get active/published chapters in track order.
         */
        $chapters = $audiobook
            ->chapters()
            ->where('status', 'active')
            ->where(function ($query) {
                $query
                    ->whereNull('published_at')
                    ->orWhere(
                        'published_at',
                        '<=',
                        now()
                    );
            })
            ->orderBy('track_number')
            ->get();

        if ($chapters->isEmpty()) {
            abort(
                404,
                'Audiobook has no available chapters.'
            );
        }

        /*
         * Audiobook downloads are delivered chapter by chapter.
         *
         * At this stage we use the first chapter's file only
         * when a single downloadable file is available.
         *
         * Full audiobook packaging should be handled separately
         * once the archive/download architecture is introduced.
         */
        $chapter = $chapters->first();

        if (empty($chapter->audio_file)) {
            abort(
                404,
                'Audiobook file not found.'
            );
        }

        $disk = Storage::disk('audiobooks');

        if (! $disk->exists($chapter->audio_file)) {
            abort(
                404,
                'Audiobook file not found.'
            );
        }

        /*
         * Generate a safe download filename.
         */
        $bookTitle = $audiobook->book?->title
            ?? 'audiobook';

        $filename = str($bookTitle)
            ->slug()
            ->append('.mp3')
            ->toString();

        /*
         * Return the file from private storage.
         *
         * The storage path itself is never exposed.
         */
        return response()->streamDownload(
            function () use (
                $disk,
                $chapter
            ): void {
                $stream = $disk->readStream(
                    $chapter->audio_file
                );

                if ($stream === false) {
                    return;
                }

                while (! feof($stream)) {
                    echo fread($stream, 8192);

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();
                }

                fclose($stream);
            },
            $filename,
            [
                'Content-Type' => $disk->mimeType(
                    $chapter->audio_file
                ) ?: 'audio/mpeg',

                'Cache-Control' => 'private, no-store',

                'X-Content-Type-Options' => 'nosniff',

                'Content-Disposition' =>
                    'attachment; filename="' .
                    $filename .
                    '"',
            ]
        );
    }
}