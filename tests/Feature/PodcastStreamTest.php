<?php

namespace Tests\Feature;

use App\Models\Podcast;
use App\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PodcastStreamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A guest can stream a public podcast episode's audio.
     */
    public function test_guest_can_stream_public_podcast_audio(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'is_free' => true,
            'published_at' => now()->subDay(),
            'audio_file' => 'audio/episode-1.mp3',
            'video_file' => null,
        ]);

        $content = str_repeat('A', 1024);

        Storage::disk('podcasts')->put(
            $episode->audio_file,
            $content
        );

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertStatus(200);

        $response->assertHeader(
            'Accept-Ranges',
            'bytes'
        );

        $response->assertHeader(
            'Content-Length',
            '1024'
        );

        $response->assertHeader(
            'Content-Disposition',
            'inline'
        );

        $this->assertStringNotContainsString(
            $episode->audio_file,
            $response->getContent()
        );
    }

    /**
     * A guest can stream a public podcast episode's video.
     */
    public function test_guest_can_stream_public_podcast_video(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'is_free' => true,
            'published_at' => now()->subDay(),
            'audio_file' => null,
            'video_file' => 'video/episode-1.mp4',
        ]);

        $content = str_repeat('V', 2048);

        Storage::disk('podcasts')->put(
            $episode->video_file,
            $content
        );

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/video"
        );

        $response->assertStatus(200);

        $response->assertHeader(
            'Accept-Ranges',
            'bytes'
        );

        $response->assertHeader(
            'Content-Length',
            '2048'
        );

        $response->assertHeader(
            'Content-Disposition',
            'inline'
        );
    }

    /**
     * Draft episodes cannot be streamed.
     */
    public function test_draft_episode_cannot_be_streamed(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'draft',
            'published_at' => null,
            'audio_file' => 'audio/draft.mp3',
        ]);

        Storage::disk('podcasts')->put(
            $episode->audio_file,
            str_repeat('A', 1000)
        );

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertNotFound();
    }

    /**
     * Inactive episodes cannot be streamed.
     */
    public function test_inactive_episode_cannot_be_streamed(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'inactive',
            'published_at' => now()->subDay(),
            'audio_file' => 'audio/inactive.mp3',
        ]);

        Storage::disk('podcasts')->put(
            $episode->audio_file,
            str_repeat('A', 1000)
        );

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertNotFound();
    }

    /**
     * Future episodes cannot be streamed.
     */
    public function test_future_episode_cannot_be_streamed(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->addDay(),
            'audio_file' => 'audio/future.mp3',
        ]);

        Storage::disk('podcasts')->put(
            $episode->audio_file,
            str_repeat('A', 1000)
        );

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertNotFound();
    }

    /**
     * Episodes belonging to an inactive podcast cannot be streamed.
     */
    public function test_episode_cannot_be_streamed_when_podcast_is_inactive(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'inactive',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'audio_file' => 'audio/inactive-podcast.mp3',
        ]);

        Storage::disk('podcasts')->put(
            $episode->audio_file,
            str_repeat('A', 1000)
        );

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertNotFound();
    }

    /**
     * Episodes belonging to a future podcast cannot be streamed.
     */
    public function test_episode_cannot_be_streamed_when_podcast_is_future(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'audio_file' => 'audio/future-podcast.mp3',
        ]);

        Storage::disk('podcasts')->put(
            $episode->audio_file,
            str_repeat('A', 1000)
        );

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertNotFound();
    }

    /**
     * An episode from another podcast cannot be accessed
     * through the wrong podcast URL.
     */
    public function test_episode_must_belong_to_requested_podcast(): void
    {
        Storage::fake('podcasts');

        $podcastOne = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $podcastTwo = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcastTwo->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'audio_file' => 'audio/episode.mp3',
        ]);

        Storage::disk('podcasts')->put(
            $episode->audio_file,
            str_repeat('A', 1000)
        );

        $response = $this->get(
            "/api/podcasts/{$podcastOne->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertNotFound();
    }

    /**
     * Missing audio files return 404.
     */
    public function test_missing_audio_file_returns_not_found(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'audio_file' => 'audio/missing.mp3',
        ]);

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertNotFound();
    }

    /**
     * Missing video files return 404.
     */
    public function test_missing_video_file_returns_not_found(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'video_file' => 'video/missing.mp4',
        ]);

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/video"
        );

        $response->assertNotFound();
    }

    /**
     * Audio streaming supports HTTP Range requests.
     */
    public function test_audio_supports_range_requests(): void
    {
        Storage::fake('podcasts');

        $content = str_repeat('A', 5000);

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'audio_file' => 'audio/range.mp3',
        ]);

        Storage::disk('podcasts')->put(
            $episode->audio_file,
            $content
        );

        $response = $this->withHeaders([
            'Range' => 'bytes=0-999',
        ])->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertStatus(206);

        $response->assertHeader(
            'Accept-Ranges',
            'bytes'
        );

        $response->assertHeader(
            'Content-Length',
            '1000'
        );

        $response->assertHeader(
            'Content-Range',
            'bytes 0-999/5000'
        );

    }

    /**
     * Video streaming supports HTTP Range requests.
     */
    public function test_video_supports_range_requests(): void
    {
        Storage::fake('podcasts');

        $content = str_repeat('V', 5000);

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'video_file' => 'video/range.mp4',
        ]);

        Storage::disk('podcasts')->put(
            $episode->video_file,
            $content
        );

        $response = $this->withHeaders([
            'Range' => 'bytes=1000-1999',
        ])->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/video"
        );

        $response->assertStatus(206);

        $response->assertHeader(
            'Accept-Ranges',
            'bytes'
        );

        $response->assertHeader(
            'Content-Length',
            '1000'
        );

        $response->assertHeader(
            'Content-Range',
            'bytes 1000-1999/5000'
        );

    }

    /**
     * Invalid audio ranges return 416.
     */
    public function test_invalid_audio_range_returns_416(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'audio_file' => 'audio/invalid-range.mp3',
        ]);

        Storage::disk('podcasts')->put(
            $episode->audio_file,
            str_repeat('A', 1000)
        );

        $response = $this->withHeaders([
            'Range' => 'bytes=5000-6000',
        ])->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertStatus(416);

        $response->assertHeader(
            'Content-Range',
            'bytes */1000'
        );
    }

    /**
     * Invalid video ranges return 416.
     */
    public function test_invalid_video_range_returns_416(): void
    {
        Storage::fake('podcasts');

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'video_file' => 'video/invalid-range.mp4',
        ]);

        Storage::disk('podcasts')->put(
            $episode->video_file,
            str_repeat('V', 1000)
        );

        $response = $this->withHeaders([
            'Range' => 'bytes=5000-6000',
        ])->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/video"
        );

        $response->assertStatus(416);

        $response->assertHeader(
            'Content-Range',
            'bytes */1000'
        );
    }

    /**
     * The private audio path is never exposed
     * in the streaming response.
     */
    public function test_private_audio_path_is_not_exposed(): void
    {
        Storage::fake('podcasts');

        $privatePath = 'private/audio/secret-episode.mp3';

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'audio_file' => $privatePath,
        ]);

        Storage::disk('podcasts')->put(
            $privatePath,
            str_repeat('A', 1000)
        );

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/audio"
        );

        $response->assertSuccessful();

        $this->assertStringNotContainsString(
            $privatePath,
            $response->getContent()
        );
    }

    /**
     * The private video path is never exposed
     * in the streaming response.
     */
    public function test_private_video_path_is_not_exposed(): void
    {
        Storage::fake('podcasts');

        $privatePath = 'private/video/secret-episode.mp4';

        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
            'video_file' => $privatePath,
        ]);

        Storage::disk('podcasts')->put(
            $privatePath,
            str_repeat('V', 1000)
        );

        $response = $this->get(
            "/api/podcasts/{$podcast->uuid}/episodes/{$episode->uuid}/video"
        );

        $response->assertSuccessful();

        $this->assertStringNotContainsString(
            $privatePath,
            $response->getContent()
        );
    }
}