<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PodcastResource;
use App\Models\Podcast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PodcastController extends Controller
{
    /**
     * Display the public podcast catalogue.
     *
     * Only active and currently published podcasts
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

        $query = Podcast::query()
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
         *
         * Example:
         *
         * /api/podcasts?featured=1
         */
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        /*
         * Optional search.
         *
         * Example:
         *
         * /api/podcasts?search=faith
         */
        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(function ($query) use ($search) {
                $query
                    ->where(
                        'title',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'description',
                        'like',
                        '%' . $search . '%'
                    );
            });
        }

        $podcasts = $query
            ->withCount([
                'episodes',
                'episodes as published_episodes_count' =>
                    function ($query) {
                        $query
                            ->where(
                                'status',
                                'active'
                            )
                            ->where(function ($query) {
                                $query
                                    ->whereNull(
                                        'published_at'
                                    )
                                    ->orWhere(
                                        'published_at',
                                        '<=',
                                        now()
                                    );
                            });
                    },
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return PodcastResource::collection(
            $podcasts
        );
    }

    /**
     * Display a single public podcast.
     *
     * The response includes currently published
     * episodes.
     */
    public function show(
        Podcast $podcast
    ): PodcastResource {
        $this->ensurePubliclyAvailable(
            $podcast
        );

        $podcast->load([
            'episodes' => function ($query) {
                $query
                    ->where('status', 'active')
                    ->where(function ($query) {
                        $query
                            ->whereNull(
                                'published_at'
                            )
                            ->orWhere(
                                'published_at',
                                '<=',
                                now()
                            );
                    })
                    ->orderBy('episode_number');
            },
        ]);

        return new PodcastResource(
            $podcast
        );
    }

    /**
     * Ensure that the podcast is publicly available.
     */
    private function ensurePubliclyAvailable(
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