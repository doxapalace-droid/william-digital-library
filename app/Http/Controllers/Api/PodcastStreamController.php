<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PodcastStreamController extends Controller
{
    /**
     * Stream a podcast episode's audio.
     *
     * Podcast content is free and publicly accessible,
     * but the underlying audio file remains private.
     *
     * HTTP Range requests are supported so podcast players
     * can seek, pause, resume, and continue playback.
     */
    public function audio(
        Request $request,
        Podcast $podcast,
        PodcastEpisode $episode
    ): Response {
        $this->ensureEpisodeBelongsToPodcast(
            $podcast,
            $episode
        );

        $this->ensureEpisodeIsPubliclyAvailable(
            $podcast,
            $episode
        );

        if (! $episode->hasAudio()) {
            abort(
                404,
                'Audio is not available for this episode.'
            );
        }

        return $this->streamFile(
            $request,
            $episode,
            $episode->audio_file,
            'audio/mpeg'
        );
    }

    /**
     * Stream a podcast episode's video.
     *
     * Podcast video is free and publicly accessible,
     * but the underlying video file remains private.
     *
     * HTTP Range requests are supported so video players
     * can seek, pause, resume, and continue playback.
     */
    public function video(
        Request $request,
        Podcast $podcast,
        PodcastEpisode $episode
    ): Response {
        $this->ensureEpisodeBelongsToPodcast(
            $podcast,
            $episode
        );

        $this->ensureEpisodeIsPubliclyAvailable(
            $podcast,
            $episode
        );

        if (! $episode->hasVideo()) {
            abort(
                404,
                'Video is not available for this episode.'
            );
        }

        return $this->streamFile(
            $request,
            $episode,
            $episode->video_file,
            'video/mp4'
        );
    }

    /**
     * Ensure the episode belongs to the podcast
     * specified in the URL.
     *
     * This prevents an episode belonging to another
     * podcast from being accessed through a different
     * podcast URL.
     */
    private function ensureEpisodeBelongsToPodcast(
        Podcast $podcast,
        PodcastEpisode $episode
    ): void {
        if (
            (int) $episode->podcast_id
            !== (int) $podcast->id
        ) {
            abort(
                404,
                'Podcast episode not found.'
            );
        }
    }

    /**
     * Ensure both the podcast and episode are
     * currently publicly available.
     */
    private function ensureEpisodeIsPubliclyAvailable(
        Podcast $podcast,
        PodcastEpisode $episode
    ): void {
        /*
         * The podcast itself must be active and published.
         */
        if (! $podcast->isActive()) {
            abort(
                404,
                'Podcast not found.'
            );
        }

        /*
         * The episode must also be active,
         * published, and publicly available.
         */
        if (! $episode->isPubliclyAvailable()) {
            abort(
                404,
                'Podcast episode not found.'
            );
        }
    }

    /**
     * Stream a private podcast media file.
     *
     * Supports:
     *
     * bytes=0-999
     * bytes=1000-1999
     * bytes=1000-
     * bytes=-1000
     */
    private function streamFile(
        Request $request,
        PodcastEpisode $episode,
        string $filePath,
        string $fallbackMimeType
    ): Response {
        /*
         * Podcast files must remain on the private
         * podcasts filesystem.
         */
        $disk = Storage::disk('podcasts');

        /*
         * Never attempt to stream an empty or missing
         * file.
         */
        if (
            $filePath === ''
            || ! $disk->exists($filePath)
        ) {
            abort(
                404,
                'Media file not found.'
            );
        }

        /*
         * Get the file size.
         */
        $size = $disk->size($filePath);

        if ($size <= 0) {
            abort(
                404,
                'Media file is empty.'
            );
        }

        /*
         * Detect the MIME type from the storage disk.
         *
         * Fall back to the media type supplied by the
         * calling method.
         */
        $mimeType = $disk->mimeType($filePath)
            ?: $fallbackMimeType;

        /*
         * Determine whether the browser/player has
         * requested a specific byte range.
         */
        $range = $request->header('Range');

        /*
         * No Range header:
         *
         * Return the complete media file.
         */
        if ($range === null) {
            return $this->fullResponse(
                $disk,
                $filePath,
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
                'Content-Range' =>
                    "bytes */{$size}",

                'Accept-Ranges' =>
                    'bytes',

                'Cache-Control' =>
                    'private, no-store',
            ]);
        }

        [$start, $end] = $parsedRange;

        $length = $end - $start + 1;

        return $this->partialResponse(
            $disk,
            $filePath,
            $start,
            $end,
            $length,
            $size,
            $mimeType
        );
    }

    /**
     * Return the complete media file.
     */
    private function fullResponse(
        $disk,
        string $filePath,
        int $size,
        string $mimeType
    ): Response {
        return response()->stream(
            function () use (
                $disk,
                $filePath
            ): void {
                $stream = $disk->readStream(
                    $filePath
                );

                if ($stream === false) {
                    return;
                }

                while (! feof($stream)) {
                    $data = fread(
                        $stream,
                        8192
                    );

                    if (
                        $data === false
                        || $data === ''
                    ) {
                        break;
                    }

                    echo $data;

                    if (
                        ob_get_level() > 0
                    ) {
                        ob_flush();
                    }

                    flush();
                }

                fclose($stream);
            },
            200,
            [
                'Content-Type' =>
                    $mimeType,

                'Content-Length' =>
                    $size,

                'Content-Disposition' =>
                    'inline',

                'Accept-Ranges' =>
                    'bytes',

                'Cache-Control' =>
                    'private, no-store',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    /**
     * Return a partial byte range of the media file.
     */
    private function partialResponse(
        $disk,
        string $filePath,
        int $start,
        int $end,
        int $length,
        int $size,
        string $mimeType
    ): Response {
        return response()->stream(
            function () use (
                $disk,
                $filePath,
                $start,
                $length
            ): void {
                $stream = $disk->readStream(
                    $filePath
                );

                if ($stream === false) {
                    return;
                }

                /*
                 * Move the stream to the requested
                 * starting byte.
                 */
                if (
                    fseek(
                        $stream,
                        $start
                    ) !== 0
                ) {
                    fclose($stream);

                    return;
                }

                $remaining = $length;

                while (
                    $remaining > 0
                    && ! feof($stream)
                ) {
                    $chunkSize = min(
                        8192,
                        $remaining
                    );

                    $data = fread(
                        $stream,
                        $chunkSize
                    );

                    if (
                        $data === false
                        || $data === ''
                    ) {
                        break;
                    }

                    echo $data;

                    $remaining -= strlen(
                        $data
                    );

                    if (
                        ob_get_level() > 0
                    ) {
                        ob_flush();
                    }

                    flush();
                }

                fclose($stream);
            },
            206,
            [
                'Content-Type' =>
                    $mimeType,

                'Content-Length' =>
                    $length,

                'Content-Range' =>
                    "bytes {$start}-{$end}/{$size}",

                'Content-Disposition' =>
                    'inline',

                'Accept-Ranges' =>
                    'bytes',

                'Cache-Control' =>
                    'private, no-store',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    /**
     * Parse an HTTP Range header.
     *
     * Supported examples:
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
        /*
         * Only byte ranges are supported.
         */
        if (
            ! str_starts_with(
                $range,
                'bytes='
            )
        ) {
            return null;
        }

        $range = substr(
            $range,
            6
        );

        /*
         * We currently support one range
         * at a time.
         */
        if (
            str_contains(
                $range,
                ','
            )
        ) {
            return null;
        }

        [
            $start,
            $end
        ] = array_pad(
            explode(
                '-',
                $range,
                2
            ),
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

            if (
                $suffixLength <= 0
            ) {
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
        if (
            $start < 0
            || $start >= $size
        ) {
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
        if (
            $end < $start
        ) {
            return null;
        }

        /*
         * Clamp an end position that extends
         * beyond the file.
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