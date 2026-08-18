<?php

namespace Tests\Feature;

use App\Models\Podcast;
use App\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodcastEpisodeApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Public episode details can be viewed.
     */
    public function test_public_episode_details_can_be_viewed(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'uuid' => $episode->uuid,
            'title' => $episode->title,
            'slug' => $episode->slug,
        ]);
    }

    /**
     * Draft episodes are not publicly accessible.
     */
    public function test_draft_episode_is_not_publicly_accessible(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'draft',
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Inactive episodes are not publicly accessible.
     */
    public function test_inactive_episode_is_not_publicly_accessible(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'inactive',
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Future episodes are not publicly accessible.
     */
    public function test_future_episode_is_not_publicly_accessible(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * An episode is not publicly accessible when
     * its podcast is inactive.
     */
    public function test_episode_is_not_publicly_accessible_when_podcast_is_inactive(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'inactive',
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * An episode is not publicly accessible when
     * its podcast is scheduled for the future.
     */
    public function test_episode_is_not_publicly_accessible_when_podcast_is_future(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Free episodes expose public metadata.
     */
    public function test_free_episode_exposes_public_metadata(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'is_free' => true,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'uuid' => $episode->uuid,
            'is_free' => true,
        ]);
    }

    /**
     * Audio episodes expose audio availability information
     * without exposing the private audio file path.
     */
    public function test_audio_episode_does_not_expose_private_audio_path(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'audio_file' => 'podcasts/audio/episode-1.mp3',
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertSuccessful();

        $response->assertJsonMissing([
            'audio_file' => 'podcasts/audio/episode-1.mp3',
        ]);
    }

    /**
     * Video episodes expose video availability information
     * without exposing the private video file path.
     */
    public function test_video_episode_does_not_expose_private_video_path(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'video_file' => 'podcasts/video/episode-1.mp4',
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertSuccessful();

        $response->assertJsonMissing([
            'video_file' => 'podcasts/video/episode-1.mp4',
        ]);
    }

    /**
     * Episode artwork is returned when supplied.
     */
    public function test_episode_artwork_is_returned(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
            'cover_image' => 'podcasts/default.jpg',
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'cover_image' => 'podcasts/episode-1.jpg',
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'cover_image' => 'podcasts/episode-1.jpg',
        ]);
    }

    /**
     * Episode artwork falls back to the podcast artwork
     * when the episode has no artwork of its own.
     */
    public function test_episode_artwork_falls_back_to_podcast_artwork(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
            'cover_image' => 'podcasts/default.jpg',
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'cover_image' => null,
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'cover_image' => 'podcasts/default.jpg',
        ]);
    }

    /**
     * Guests can access free podcast episodes.
     */
    public function test_guest_can_access_free_episode(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'is_free' => true,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}"
        );

        $response->assertSuccessful();
    }
}