<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PodcastEpisodeProgressResource;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Models\PodcastEpisodeProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class PodcastEpisodeProgressController extends Controller
{
    /**
     * Display the authenticated user's progress
     * for a specific podcast episode.
     */
    public function show(
        Request $request,
        Podcast $podcast,
        PodcastEpisode $episode
    ): PodcastEpisodeProgressResource {
        $this->ensureEpisodeBelongsToPodcast(
            $podcast,
            $episode
        );

        $this->ensureEpisodeIsPubliclyAvailable(
            $episode
        );

        $progress = PodcastEpisodeProgress::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'podcast_episode_id',
                $episode->id
            )
            ->first();

        if ($progress === null) {
            $progress = new PodcastEpisodeProgress([
                'user_id' => $request->user()->id,
                'podcast_episode_id' => $episode->id,
                'position_seconds' => 0,
                'duration_seconds' => (int) (
                    $episode->duration_seconds ?? 0
                ),
                'progress_percent' => 0,
                'is_completed' => false,
                'last_played_at' => null,
            ]);
        }

        return new PodcastEpisodeProgressResource(
            $progress
        );
    }

    /**
     * Save or update playback progress.
     */
    public function update(
        Request $request,
        Podcast $podcast,
        PodcastEpisode $episode
    ): PodcastEpisodeProgressResource {
        $this->ensureEpisodeBelongsToPodcast(
            $podcast,
            $episode
        );

        $this->ensureEpisodeIsPubliclyAvailable(
            $episode
        );

        $validated = Validator::make(
            $request->all(),
            [
                'position_seconds' => [
                    'required',
                    'integer',
                    'min:0',
                ],

                'duration_seconds' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],

                /*
                 * Accepted as API input for compatibility,
                 * but NOT stored as a database column.
                 */
                'progress_percentage' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:100',
                ],

                /*
                 * Accepted as API input for compatibility,
                 * but completion is calculated server-side.
                 */
                'completed' => [
                    'nullable',
                    'boolean',
                ],
            ]
        )->validate();

        /*
         * The episode's own duration is authoritative.
         */
        $duration = max(
            0,
            (int) ($episode->duration_seconds ?? 0)
        );

        /*
         * Never allow position beyond the episode duration.
         */
        $position = min(
            (int) $validated['position_seconds'],
            $duration
        );

        /*
         * Calculate percentage on the server.
         */
        if ($duration > 0) {
            $percentage = round(
                ($position / $duration) * 100,
                2
            );

            $percentage = min(
                100,
                max(0, $percentage)
            );
        } else {
            $percentage = 0;
        }

        /*
         * Completion is determined from playback position.
         */
        $completed = (
            $duration > 0
            && $position >= $duration
        );

        if ($completed) {
            $percentage = 100;
        }

        $progress = PodcastEpisodeProgress::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'podcast_episode_id',
                $episode->id
            )
            ->first();

        if ($progress === null) {
            $progress = new PodcastEpisodeProgress();

            $progress->user_id =
                $request->user()->id;

            $progress->podcast_episode_id =
                $episode->id;
        }

        /*
         * Actual DATABASE fields.
         */
        $progress->position_seconds = $position;

        $progress->duration_seconds = $duration;

        $progress->progress_percent = $percentage;

        $progress->is_completed = $completed;

        $progress->last_played_at = now();

        $progress->save();

        return new PodcastEpisodeProgressResource(
            $progress->fresh()
        );
    }

    /**
     * Mark an episode as completed.
     */
    public function complete(
        Request $request,
        Podcast $podcast,
        PodcastEpisode $episode
    ): PodcastEpisodeProgressResource {
        $this->ensureEpisodeBelongsToPodcast(
            $podcast,
            $episode
        );

        $this->ensureEpisodeIsPubliclyAvailable(
            $episode
        );

        $duration = max(
            0,
            (int) ($episode->duration_seconds ?? 0)
        );

        $progress = PodcastEpisodeProgress::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'podcast_episode_id',
                $episode->id
            )
            ->first();

        if ($progress === null) {
            $progress = new PodcastEpisodeProgress();

            $progress->user_id =
                $request->user()->id;

            $progress->podcast_episode_id =
                $episode->id;
        }

        /*
         * Actual DATABASE fields.
         */
        $progress->position_seconds = $duration;

        $progress->duration_seconds = $duration;

        $progress->progress_percent = $duration > 0
            ? 100
            : 0;

        $progress->is_completed = $duration > 0;

        $progress->last_played_at = now();

        $progress->save();

        return new PodcastEpisodeProgressResource(
            $progress->fresh()
        );
    }

    /**
     * Return the authenticated user's
     * unfinished podcast episodes.
     */
    public function continueListening(
        Request $request
    ): AnonymousResourceCollection {
        $progress = PodcastEpisodeProgress::query()
            ->where(
                'user_id',
                $request->user()->id
            )

            /*
             * Actual DATABASE field.
             */
            ->where(
                'is_completed',
                false
            )

            ->where(
                'position_seconds',
                '>',
                0
            )

            ->whereHas(
                'podcastEpisode',
                function ($query) {
                    $query
                        ->where(
                            'status',
                            'active'
                        )

                        ->where(
                            function ($query) {
                                $query
                                    ->whereNull(
                                        'published_at'
                                    )
                                    ->orWhere(
                                        'published_at',
                                        '<=',
                                        now()
                                    );
                            }
                        )

                        ->whereHas(
                            'podcast',
                            function ($query) {
                                $query
                                    ->where(
                                        'status',
                                        'active'
                                    )

                                    ->where(
                                        function ($query) {
                                            $query
                                                ->whereNull(
                                                    'published_at'
                                                )
                                                ->orWhere(
                                                    'published_at',
                                                    '<=',
                                                    now()
                                                );
                                        }
                                    );
                            }
                        );
                }
            )

            ->with([
                'podcastEpisode.podcast',
            ])

            ->latest(
                'last_played_at'
            )

            ->paginate(10);

        return PodcastEpisodeProgressResource::collection(
            $progress
        );
    }

    /**
     * Ensure the episode belongs to the requested podcast.
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
     * Ensure the episode and podcast are publicly available.
     */
    private function ensureEpisodeIsPubliclyAvailable(
        PodcastEpisode $episode
    ): void {
        if (! $episode->isPubliclyAvailable()) {
            abort(
                404,
                'Podcast episode not found.'
            );
        }
    }
}