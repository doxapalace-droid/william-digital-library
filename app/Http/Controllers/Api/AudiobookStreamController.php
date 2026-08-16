<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudiobookChapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AudiobookStreamController extends Controller
{
    /**
     * Stream an audiobook chapter to an entitled customer.
     *
     * Supports HTTP Range requests so audiobook players
     * can seek, pause, resume, and continue playback.
     */
    public function stream(
        Request $request,
        AudiobookChapter $chapter
    ): Response {
        $user = $request->user();

        /*
         * The chapter must belong to an audiobook that the
         * authenticated customer is entitled to stream.
         */
        $audiobook = $chapter->audiobook;

        if ($audiobook === null) {
            abort(404, 'Audiobook not found.');
        }

        $entitlement = $audiobook
            ->entitlements()
            ->where('user_id', $user->id)
            ->first();

        if (
            $entitlement === null
            || ! $entitlement->canStream()
        ) {
            abort(
                403,
                'You do not have permission to stream this audiobook.'
            );
        }

        /*
         * The audiobook itself must be active and published.
         */
        if (! $audiobook->isActive()) {
            abort(404, 'Audiobook is not available.');
        }

        /*
         * The individual chapter must also be active
         * and published.
         */
        if (! $chapter->isActive()) {
            abort(404, 'Chapter is not available.');
        }

        /*
         * Audiobook files are stored on the private
         * audiobooks filesystem.
         */
        $disk = Storage::disk('audiobooks');

        if (! $disk->exists($chapter->audio_file)) {
            abort(404, 'Audio file not found.');
        }

        /*
         * Get file information.
         */
        $size = $disk->size($chapter->audio_file);

        if ($size <= 0) {
            abort(404, 'Audio file is empty.');
        }

        $mimeType = $disk->mimeType($chapter->audio_file)
            ?: 'audio/mpeg';

        /*
         * Determine the requested byte range.
         */
        $range = $request->header('Range');

        /*
         * No Range header:
         *
         * Return the complete audio file.
         */
        if ($range === null) {
            return $this->fullResponse(
                $disk,
                $chapter,
                $size,
                $mimeType
            );
        }

        /*
         * Parse the requested range.
         */
        $parsedRange = $this->parseRange(
            $range,
            $size
        );

        /*
         * Invalid or unsatisfiable range.
         */
        if ($parsedRange === null) {
            return response('', 416, [
                'Content-Range' => "bytes */{$size}",
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        [$start, $end] = $parsedRange;

        $length = $end - $start + 1;

        return $this->partialResponse(
            $disk,
            $chapter,
            $start,
            $end,
            $length,
            $size,
            $mimeType
        );
    }

    /**
     * Return the complete audio file.
     */
    private function fullResponse(
        $disk,
        AudiobookChapter $chapter,
        int $size,
        string $mimeType
    ): Response {
        return response()->stream(
            function () use ($disk, $chapter): void {
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
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => $size,
                'Content-Disposition' => 'inline',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Return a partial byte range of the audio file.
     */
    private function partialResponse(
        $disk,
        AudiobookChapter $chapter,
        int $start,
        int $end,
        int $length,
        int $size,
        string $mimeType
    ): Response {
        return response()->stream(
            function () use (
                $disk,
                $chapter,
                $start,
                $length
            ): void {
                $stream = $disk->readStream(
                    $chapter->audio_file
                );

                if ($stream === false) {
                    return;
                }

                /*
                 * Move the stream to the requested
                 * starting byte.
                 */
                if (fseek($stream, $start) !== 0) {
                    fclose($stream);
                    return;
                }

                $remaining = $length;

                while ($remaining > 0 && ! feof($stream)) {
                    $chunkSize = min(
                        8192,
                        $remaining
                    );

                    $data = fread(
                        $stream,
                        $chunkSize
                    );

                    if ($data === false || $data === '') {
                        break;
                    }

                    echo $data;

                    $remaining -= strlen($data);

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();
                }

                fclose($stream);
            },
            206,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => $length,
                'Content-Range' => "bytes {$start}-{$end}/{$size}",
                'Content-Disposition' => 'inline',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Parse an HTTP Range header.
     *
     * Supports:
     *
     * bytes=0-999
     * bytes=1000-1999
     * bytes=1000-
     * bytes=-1000
     */
    private function parseRange(
        string $range,
        int $size
    ): ?array {
        if (! str_starts_with($range, 'bytes=')) {
            return null;
        }

        $range = substr($range, 6);

        /*
         * We currently support one range at a time.
         */
        if (str_contains($range, ',')) {
            return null;
        }

        [$start, $end] = array_pad(
            explode('-', $range, 2),
            2,
            ''
        );

        /*
         * Suffix range:
         *
         * bytes=-500
         *
         * Means the final 500 bytes.
         */
        if ($start === '') {
            $suffixLength = (int) $end;

            if ($suffixLength <= 0) {
                return null;
            }

            $suffixLength = min(
                $suffixLength,
                $size
            );

            return [
                $size - $suffixLength,
                $size - 1,
            ];
        }

        $start = (int) $start;

        /*
         * Starting position cannot be beyond
         * the final byte.
         */
        if ($start < 0 || $start >= $size) {
            return null;
        }

        /*
         * Open-ended range:
         *
         * bytes=1000-
         */
        if ($end === '') {
            return [
                $start,
                $size - 1,
            ];
        }

        $end = (int) $end;

        /*
         * End cannot be before start.
         */
        if ($end < $start) {
            return null;
        }

        /*
         * A range may specify an end beyond
         * the file. Clamp it to the final byte.
         */
        $end = min(
            $end,
            $size - 1
        );

        return [
            $start,
            $end,
        ];
    }
}