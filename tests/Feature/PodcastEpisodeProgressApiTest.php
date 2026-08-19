<?php

namespace Tests\Feature;

use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Models\PodcastEpisodeProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodcastEpisodeProgressApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an active, publicly available podcast.
     */
    private function createPodcast(): Podcast
    {
        return Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subMinute(),
        ]);
    }

    /**
     * Create an active, publicly available episode.
     */
    private function createEpisode(
        Podcast $podcast,
        array $attributes = []
    ): PodcastEpisode {
        return PodcastEpisode::factory()->create(
            array_merge(
                [
                    'podcast_id' => $podcast->id,
                    'status' => 'active',
                    'published_at' => now()->subMinute(),
                    'duration_seconds' => 600,
                    'is_free' => true,
                ],
                $attributes
            )
        );
    }

    /**
     * Guest cannot view personal progress.
     */
    public function test_guest_cannot_view_episode_progress(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode($podcast);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress"
        );

        $response->assertStatus(401);
    }

    /**
     * Guest cannot update personal progress.
     */
    public function test_guest_cannot_update_episode_progress(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode($podcast);

        $response = $this->putJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress",
            [
                'position_seconds' => 120,
            ]
        );

        $response->assertStatus(401);
    }

    /**
     * Authenticated user can retrieve progress.
     */
    public function test_authenticated_user_can_view_episode_progress(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode($podcast);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.podcast_episode_id',
                $episode->id
            )
            ->assertJsonPath(
                'data.position_seconds',
                0
            )
            ->assertJsonPath(
                'data.progress_percentage',
                0
            )
            ->assertJsonPath(
                'data.completed',
                false
            );
    }

    /**
     * New progress starts at zero.
     */
    public function test_new_episode_returns_zero_progress(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 900,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                0
            )
            ->assertJsonPath(
                'data.duration_seconds',
                900
            )
            ->assertJsonPath(
                'data.progress_percentage',
                0
            )
            ->assertJsonPath(
                'data.completed',
                false
            )
            ->assertJsonPath(
                'data.has_started',
                false
            );
    }

    /**
     * User can save playback progress.
     */
    public function test_user_can_save_episode_progress(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 600,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress",
                [
                    'position_seconds' => 150,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                150
            )
            ->assertJsonPath(
                'data.duration_seconds',
                600
            )
            ->assertJsonPath(
                'data.progress_percentage',
                25
            )
            ->assertJsonPath(
                'data.completed',
                false
            )
            ->assertJsonPath(
                'data.has_started',
                true
            );

        $this->assertDatabaseHas(
            'podcast_episode_progress',
            [
                'user_id' => $user->id,
                'podcast_episode_id' => $episode->id,
                'position_seconds' => 150,
                'duration_seconds' => 600,
                'progress_percent' => 25,
                'is_completed' => false,
            ]
        );
    }

    /**
     * Progress percentage is calculated by the server.
     */
    public function test_progress_percentage_is_calculated_correctly(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 1000,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress",
                [
                    'position_seconds' => 375,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.progress_percentage',
                37.5
            );
    }

    /**
     * Client supplied percentage cannot override
     * the server calculated percentage.
     */
    public function test_server_calculates_progress_percentage(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 1000,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress",
                [
                    'position_seconds' => 200,

                    /*
                     * Deliberately incorrect client value.
                     */
                    'progress_percentage' => 99,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.progress_percentage',
                20
            );
    }

    /**
     * Position cannot exceed duration.
     */
    public function test_position_cannot_exceed_duration(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 600,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress",
                [
                    'position_seconds' => 1000,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                600
            )
            ->assertJsonPath(
                'data.progress_percentage',
                100
            )
            ->assertJsonPath(
                'data.completed',
                true
            );
    }

    /**
     * Repeated updates modify the existing record.
     */
    public function test_repeated_updates_do_not_create_duplicate_progress(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 1000,
            ]
        );

        $url =
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress";

        $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                $url,
                [
                    'position_seconds' => 100,
                ]
            )
            ->assertOk();

        $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                $url,
                [
                    'position_seconds' => 500,
                ]
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'podcast_episode_progress',
            1
        );

        $this->assertDatabaseHas(
            'podcast_episode_progress',
            [
                'user_id' => $user->id,
                'podcast_episode_id' => $episode->id,
                'position_seconds' => 500,
            ]
        );
    }

    /**
     * User can mark an episode as completed.
     */
    public function test_user_can_mark_episode_completed(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 600,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress/complete"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.position_seconds',
                600
            )
            ->assertJsonPath(
                'data.duration_seconds',
                600
            )
            ->assertJsonPath(
                'data.progress_percentage',
                100
            )
            ->assertJsonPath(
                'data.completed',
                true
            )
            ->assertJsonPath(
                'data.is_completed',
                true
            );

        $this->assertDatabaseHas(
            'podcast_episode_progress',
            [
                'user_id' => $user->id,
                'podcast_episode_id' => $episode->id,
                'is_completed' => true,
                'progress_percent' => 100,
            ]
        );
    }

    /**
     * Started episode appears in Continue Listening.
     */
    public function test_started_episode_appears_in_continue_listening(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 1000,
            ]
        );

        PodcastEpisodeProgress::factory()->create([
            'user_id' => $user->id,
            'podcast_episode_id' => $episode->id,
            'position_seconds' => 300,
            'duration_seconds' => 1000,
            'progress_percent' => 30,
            'is_completed' => false,
            'last_played_at' => now(),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                '/api/podcast-progress/continue-listening'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.podcast_episode_id',
                $episode->id
            );
    }

    /**
     * Completed episode does not appear in Continue Listening.
     */
    public function test_completed_episode_does_not_appear_in_continue_listening(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 1000,
            ]
        );

        PodcastEpisodeProgress::factory()->create([
            'user_id' => $user->id,
            'podcast_episode_id' => $episode->id,
            'position_seconds' => 1000,
            'duration_seconds' => 1000,
            'progress_percent' => 100,
            'is_completed' => true,
            'last_played_at' => now(),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                '/api/podcast-progress/continue-listening'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );
    }

    /**
     * User's Continue Listening list does not include
     * another user's progress.
     */
    public function test_continue_listening_only_returns_authenticated_users_progress(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 1000,
            ]
        );

        PodcastEpisodeProgress::factory()->create([
            'user_id' => $otherUser->id,
            'podcast_episode_id' => $episode->id,
            'position_seconds' => 400,
            'duration_seconds' => 1000,
            'progress_percent' => 40,
            'is_completed' => false,
            'last_played_at' => now(),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                '/api/podcast-progress/continue-listening'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );
    }

    /**
     * User cannot access an episode through the wrong podcast.
     */
    public function test_episode_must_belong_to_requested_podcast(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $otherPodcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $otherPodcast
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress"
            );

        $response->assertNotFound();
    }

    /**
     * Draft episode progress cannot be accessed.
     */
    public function test_draft_episode_progress_is_not_accessible(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'draft',
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress"
            );

        $response->assertNotFound();
    }

    /**
     * Inactive episode progress cannot be accessed.
     */
    public function test_inactive_episode_progress_is_not_accessible(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'inactive',
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress"
            );

        $response->assertNotFound();
    }

    /**
     * Future episode progress cannot be accessed.
     */
    public function test_future_episode_progress_is_not_accessible(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'published_at' => now()->addDay(),
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress"
            );

        $response->assertNotFound();
    }

    /**
     * Inactive podcast prevents episode progress access.
     */
    public function test_episode_progress_is_not_accessible_when_podcast_is_inactive(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $podcast->update([
            'status' => 'inactive',
        ]);

        $episode = $this->createEpisode($podcast);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress"
            );

        $response->assertNotFound();
    }

    /**
     * Invalid progress input is rejected.
     */
    public function test_invalid_progress_input_is_rejected(): void
    {
        $user = User::factory()->create();

        $podcast = $this->createPodcast();

        $episode = $this->createEpisode($podcast);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/progress",
                [
                    'position_seconds' => -10,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'position_seconds',
            ]);
    }
}