<?php

namespace Tests\Feature;

use App\Models\Podcast;
use App\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodcastApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Public podcast catalogue returns active podcasts.
     */
    public function test_public_podcast_catalogue_returns_active_podcasts(): void
    {
        $activePodcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        Podcast::factory()->create([
            'status' => 'draft',
        ]);

        Podcast::factory()->create([
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/podcasts');

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'uuid' => $activePodcast->uuid,
            'title' => $activePodcast->title,
        ]);
    }

    /**
     * Draft podcasts are not publicly listed.
     */
    public function test_draft_podcast_is_not_publicly_listed(): void
    {
        $draftPodcast = Podcast::factory()->create([
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/podcasts');

        $response->assertSuccessful();

        $response->assertJsonMissing([
            'uuid' => $draftPodcast->uuid,
        ]);
    }

    /**
     * Inactive podcasts are not publicly listed.
     */
    public function test_inactive_podcast_is_not_publicly_listed(): void
    {
        $inactivePodcast = Podcast::factory()->create([
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/podcasts');

        $response->assertSuccessful();

        $response->assertJsonMissing([
            'uuid' => $inactivePodcast->uuid,
        ]);
    }

    /**
     * Future podcasts are not publicly listed.
     */
    public function test_future_podcast_is_not_publicly_listed(): void
    {
        $futurePodcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/podcasts');

        $response->assertSuccessful();

        $response->assertJsonMissing([
            'uuid' => $futurePodcast->uuid,
        ]);
    }

    /**
     * Public podcast details can be viewed.
     */
    public function test_public_podcast_details_can_be_viewed(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}"
        );

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'uuid' => $podcast->uuid,
            'title' => $podcast->title,
            'slug' => $podcast->slug,
        ]);
    }

    /**
     * Draft podcast details are not publicly accessible.
     */
    public function test_draft_podcast_details_return_not_found(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'draft',
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Inactive podcast details are not publicly accessible.
     */
    public function test_inactive_podcast_details_return_not_found(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'inactive',
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Future podcast details are not publicly accessible.
     */
    public function test_future_podcast_details_return_not_found(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Podcast episodes can be listed publicly.
     */
    public function test_public_podcast_episodes_can_be_listed(): void
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
            "/api/podcasts/{$podcast->uuid}/episodes"
        );

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'uuid' => $episode->uuid,
            'title' => $episode->title,
        ]);
    }

    /**
     * A podcast with no public episodes returns an empty
     * episode collection.
     */
    public function test_podcast_with_no_public_episodes_returns_empty_collection(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'draft',
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes"
        );

        $response->assertSuccessful();
    }

    /**
     * Podcast episodes are not publicly listed when
     * the podcast itself is inactive.
     */
    public function test_episodes_are_not_public_when_podcast_is_inactive(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'inactive',
        ]);

        PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/podcasts/{$podcast->uuid}/episodes"
        );

        $response->assertNotFound();
    }

    /**
     * Featured podcasts can be returned by the public catalogue.
     */
    public function test_featured_podcast_is_returned(): void
    {
        $podcast = Podcast::factory()->create([
            'status' => 'active',
            'is_featured' => true,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/podcasts');

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'uuid' => $podcast->uuid,
        ]);
    }

    /**
     * Guests can access the public podcast catalogue.
     */
    public function test_guest_can_access_public_podcast_catalogue(): void
    {
        Podcast::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/podcasts');

        $response->assertSuccessful();
    }
}