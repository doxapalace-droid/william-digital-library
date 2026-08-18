<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PodcastEpisodeResource;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PodcastEpisodeController extends Controller
{
    /**
     * Display the public episodes belonging to a podcast.
     *
     * Only active and currently published episodes
     * are returned.
     */
    public function index(
        Request $request,
        Podcast $podcast
    ): AnonymousResourceCollection {
        $this->ensurePodcastIsPublic(
            $podcast
        );

        $perPage = min(
            max(
                (int) $request->input('per_page', 20),
                1
            ),
            50
        );

        $query = $podcast
            ->episodes()
            ->where('status', 'active')
            ->where(function ($query) {
                $query
                    ->whereNull('published_at')
                    ->orWhere(
                        'published_at',
                        '<=',
                        now()
                    );
            });

        /*
         * Optional featured filter.
         */
        if ($request->boolean('featured')) {
            $query->where(
                'is_featured',
                true
            );
        }

        /*
         * Optional free filter.
         *
         * Podcasts are currently designed as a
         * free content area, but retaining this
         * filter makes the API future-ready.
         */
        if ($request->has('free')) {
            $query->where(
                'is_free',
                $request->boolean('free')
            );
        }

        $episodes = $query
            ->with('podcast')
            ->orderBy('episode_number')
            ->paginate($perPage)
            ->withQueryString();

        return PodcastEpisodeResource::collection(
            $episodes
        );
    }

    /**
     * Display one public podcast episode.
     */
    public function show(
        Podcast $podcast,
        PodcastEpisode $episode
    ): PodcastEpisodeResource {
        $this->ensurePodcastIsPublic(
            $podcast
        );

        /*
         * Prevent an episode belonging to another
         * podcast from being accessed through this URL.
         */
        if (
            (int) $episode->podcast_id
            !== (int) $podcast->id
        ) {
            abort(
                404,
                'Podcast episode not found.'
            );
        }

        $episode->load('podcast');

        if (
            ! $episode->isPubliclyAvailable()
        ) {
            abort(
                404,
                'Podcast episode not found.'
            );
        }

        return new PodcastEpisodeResource(
            $episode
        );
    }

    /**
     * Ensure the parent podcast is public.
     */
    private function ensurePodcastIsPublic(
        Podcast $podcast
    ): void {
        if (! $podcast->isPubliclyAvailable()) {
            abort(
                404,
                'Podcast not found.'
            );
        }
    }
}