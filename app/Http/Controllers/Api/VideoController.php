<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VideoController extends Controller
{
    /**
     * Display the public video catalogue.
     *
     * Only active and currently published videos
     * are returned.
     */
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $perPage = min(
            max(
                (int) $request->input('per_page', 12),
                1
            ),
            50
        );

        $videos = Video::query()
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
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return VideoResource::collection(
            $videos
        );
    }

    /**
     * Display a single public video.
     *
     * Only active and currently published videos
     * are publicly accessible.
     */
    public function show(
        Video $video
    ): VideoResource {
        $this->ensurePubliclyAvailable($video);

        return new VideoResource(
            $video
        );
    }

    /**
     * Ensure that a video is publicly available.
     */
    private function ensurePubliclyAvailable(
        Video $video
    ): void {
        if (! $video->isActive()) {
            abort(
                404,
                'Video not found.'
            );
        }
    }
}