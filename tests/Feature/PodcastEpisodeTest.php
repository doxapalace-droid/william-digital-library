<?php

namespace Tests\Feature;

use App\Models\Podcast;
use App\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodcastEpisodeTest extends TestCase
{
    use RefreshDatabase;

    private function createPodcast(
        array $attributes = []
    ): Podcast {
        return Podcast::create(array_merge([
            'title' => 'Doxa Palace Podcast',
            'slug' => 'doxa-palace-podcast',
            'description' => 'A kingdom podcast.',
            'status' => 'active',
            'is_featured' => false,
            'published_at' => now(),
            'cover_image' => 'podcasts/default.jpg',
        ], $attributes));
    }

    private function createEpisode(
        Podcast $podcast,
        array $attributes = []
    ): PodcastEpisode {
        return PodcastEpisode::create(array_merge([
            'podcast_id' => $podcast->id,
            'title' => 'Episode One',
            'slug' => 'episode-one',
            'description' => 'Podcast episode description.',
            'cover_image' => null,
            'audio_file' => 'podcasts/audio/episode-one.mp3',
            'video_file' => 'podcasts/video/episode-one.mp4',
            'duration_seconds' => 3600,
            'status' => 'active',
            'is_free' => true,
            'is_featured' => false,
            'episode_number' => 1,
            'published_at' => now(),
        ], $attributes));
    }

    public function test_episode_can_be_created(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode($podcast);

        $this->assertDatabaseHas('podcast_episodes', [
            'id' => $episode->id,
            'podcast_id' => $podcast->id,
            'title' => 'Episode One',
        ]);
    }

    public function test_episode_has_uuid(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode($podcast);

        $this->assertNotNull($episode->uuid);
    }

    public function test_episode_uses_uuid_for_route_model_binding(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode($podcast);

        $this->assertSame(
            'uuid',
            $episode->getRouteKeyName()
        );
    }

    public function test_episode_belongs_to_podcast(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode($podcast);

        $this->assertTrue(
            $episode->podcast->is($podcast)
        );
    }

    public function test_podcast_has_episode(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode($podcast);

        $this->assertTrue(
            $podcast->episodes
                ->first()
                ->is($episode)
        );
    }

    public function test_episode_number_is_cast_to_integer(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'episode_number' => 5,
            ]
        );

        $this->assertIsInt(
            $episode->episode_number
        );
    }

    public function test_is_free_is_cast_to_boolean(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'is_free' => true,
            ]
        );

        $this->assertIsBool(
            $episode->is_free
        );

        $this->assertTrue(
            $episode->is_free
        );
    }

    public function test_is_featured_is_cast_to_boolean(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'is_featured' => true,
            ]
        );

        $this->assertIsBool(
            $episode->is_featured
        );

        $this->assertTrue(
            $episode->is_featured
        );
    }

    public function test_active_episode_is_active(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'active',
                'published_at' => now(),
            ]
        );

        $this->assertTrue(
            $episode->isActive()
        );
    }

    public function test_draft_episode_is_not_active(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'draft',
            ]
        );

        $this->assertFalse(
            $episode->isActive()
        );
    }

    public function test_inactive_episode_is_not_active(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'inactive',
            ]
        );

        $this->assertFalse(
            $episode->isActive()
        );
    }

    public function test_future_episode_is_not_active(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'active',
                'published_at' => now()->addDay(),
            ]
        );

        $this->assertFalse(
            $episode->isActive()
        );
    }

    public function test_episode_is_publicly_available_when_episode_and_podcast_are_active(): void
    {
        $podcast = $this->createPodcast([
            'status' => 'active',
            'published_at' => now(),
        ]);

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'active',
                'published_at' => now(),
            ]
        );

        $this->assertTrue(
            $episode->isPubliclyAvailable()
        );
    }

    public function test_episode_is_not_publicly_available_when_podcast_is_inactive(): void
    {
        $podcast = $this->createPodcast([
            'status' => 'inactive',
        ]);

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'active',
            ]
        );

        $this->assertFalse(
            $episode->isPubliclyAvailable()
        );
    }

    public function test_episode_is_not_publicly_available_when_podcast_is_future(): void
    {
        $podcast = $this->createPodcast([
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'active',
            ]
        );

        $this->assertFalse(
            $episode->isPubliclyAvailable()
        );
    }

    public function test_episode_is_not_publicly_available_when_episode_is_inactive(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'status' => 'inactive',
            ]
        );

        $this->assertFalse(
            $episode->isPubliclyAvailable()
        );
    }

    public function test_episode_can_detect_audio(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'audio_file' => 'podcasts/audio/test.mp3',
            ]
        );

        $this->assertTrue(
            $episode->hasAudio()
        );
    }

    public function test_episode_can_detect_video(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'video_file' => 'podcasts/video/test.mp4',
            ]
        );

        $this->assertTrue(
            $episode->hasVideo()
        );
    }

    public function test_episode_without_audio_reports_no_audio(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'audio_file' => null,
            ]
        );

        $this->assertFalse(
            $episode->hasAudio()
        );
    }

    public function test_episode_without_video_reports_no_video(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'video_file' => null,
            ]
        );

        $this->assertFalse(
            $episode->hasVideo()
        );
    }

    public function test_duration_is_converted_to_minutes(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'duration_seconds' => 3660,
            ]
        );

        $this->assertSame(
            61.0,
            $episode->durationInMinutes()
        );
    }

    public function test_episode_artwork_falls_back_to_podcast_artwork(): void
    {
        $podcast = $this->createPodcast([
            'cover_image' => 'podcasts/podcast-cover.jpg',
        ]);

        $episode = $this->createEpisode(
            $podcast,
            [
                'cover_image' => null,
            ]
        );

        $this->assertSame(
            'podcasts/podcast-cover.jpg',
            $episode->artwork()
        );
    }

    public function test_episode_artwork_overrides_podcast_artwork(): void
    {
        $podcast = $this->createPodcast([
            'cover_image' => 'podcasts/podcast-cover.jpg',
        ]);

        $episode = $this->createEpisode(
            $podcast,
            [
                'cover_image' => 'podcasts/episode-cover.jpg',
            ]
        );

        $this->assertSame(
            'podcasts/episode-cover.jpg',
            $episode->artwork()
        );
    }

    public function test_episode_can_have_audio_only(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'audio_file' => 'podcasts/audio/test.mp3',
                'video_file' => null,
            ]
        );

        $this->assertTrue(
            $episode->hasAudio()
        );

        $this->assertFalse(
            $episode->hasVideo()
        );
    }

    public function test_episode_can_have_video_only(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'audio_file' => null,
                'video_file' => 'podcasts/video/test.mp4',
            ]
        );

        $this->assertFalse(
            $episode->hasAudio()
        );

        $this->assertTrue(
            $episode->hasVideo()
        );
    }

    public function test_episode_can_have_both_audio_and_video(): void
    {
        $podcast = $this->createPodcast();

        $episode = $this->createEpisode(
            $podcast,
            [
                'audio_file' => 'podcasts/audio/test.mp3',
                'video_file' => 'podcasts/video/test.mp4',
            ]
        );

        $this->assertTrue(
            $episode->hasAudio()
        );

        $this->assertTrue(
            $episode->hasVideo()
        );
    }
}