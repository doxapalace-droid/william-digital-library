<?php

namespace Tests\Feature;

use App\Models\Podcast;
use App\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_podcast_can_be_created(): void
    {
        $podcast = Podcast::create([
            'title' => 'Doxa Palace Podcast',
            'slug' => 'doxa-palace-podcast',
            'description' => 'A podcast for faith, leadership, and kingdom living.',
            'cover_image' => 'podcasts/doxa-palace.jpg',
            'status' => 'active',
            'is_featured' => true,
            'published_at' => now(),
        ]);

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'title' => 'Doxa Palace Podcast',
            'slug' => 'doxa-palace-podcast',
        ]);
    }

    public function test_podcast_has_uuid(): void
    {
        $podcast = Podcast::create([
            'title' => 'Doxa Palace Podcast',
            'slug' => 'doxa-palace-podcast',
            'status' => 'active',
        ]);

        $this->assertNotNull($podcast->uuid);
    }

    public function test_podcast_uses_uuid_for_route_model_binding(): void
    {
        $podcast = Podcast::create([
            'title' => 'Doxa Palace Podcast',
            'slug' => 'doxa-palace-podcast',
            'status' => 'active',
        ]);

        $this->assertSame(
            'uuid',
            $podcast->getRouteKeyName()
        );
    }

    public function test_active_podcast_is_active(): void
    {
        $podcast = Podcast::create([
            'title' => 'Active Podcast',
            'slug' => 'active-podcast',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->assertTrue($podcast->isActive());
    }

    public function test_draft_podcast_is_not_active(): void
    {
        $podcast = Podcast::create([
            'title' => 'Draft Podcast',
            'slug' => 'draft-podcast',
            'status' => 'draft',
        ]);

        $this->assertFalse($podcast->isActive());
    }

    public function test_inactive_podcast_is_not_active(): void
    {
        $podcast = Podcast::create([
            'title' => 'Inactive Podcast',
            'slug' => 'inactive-podcast',
            'status' => 'inactive',
        ]);

        $this->assertFalse($podcast->isActive());
    }

    public function test_future_podcast_is_not_active(): void
    {
        $podcast = Podcast::create([
            'title' => 'Future Podcast',
            'slug' => 'future-podcast',
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $this->assertFalse($podcast->isActive());
    }

    public function test_featured_value_is_cast_to_boolean(): void
    {
        $podcast = Podcast::create([
            'title' => 'Featured Podcast',
            'slug' => 'featured-podcast',
            'status' => 'active',
            'is_featured' => true,
        ]);

        $this->assertIsBool($podcast->is_featured);
        $this->assertTrue($podcast->is_featured);
    }

    public function test_published_at_is_cast_to_datetime(): void
    {
        $podcast = Podcast::create([
            'title' => 'Published Podcast',
            'slug' => 'published-podcast',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $podcast->published_at
        );
    }

    public function test_podcast_has_episodes(): void
    {
        $podcast = Podcast::create([
            'title' => 'Doxa Palace Podcast',
            'slug' => 'doxa-palace-podcast',
            'status' => 'active',
        ]);

        PodcastEpisode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode One',
            'slug' => 'episode-one',
            'episode_number' => 1,
            'status' => 'active',
            'is_free' => true,
        ]);

        $this->assertCount(
            1,
            $podcast->episodes
        );

        $this->assertTrue(
            $podcast->episodes->first()->is(
                PodcastEpisode::query()->first()
            )
        );
    }

    public function test_podcast_episodes_are_ordered_by_episode_number(): void
    {
        $podcast = Podcast::create([
            'title' => 'Doxa Palace Podcast',
            'slug' => 'doxa-palace-podcast',
            'status' => 'active',
        ]);

        PodcastEpisode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode Three',
            'slug' => 'episode-three',
            'episode_number' => 3,
            'status' => 'active',
        ]);

        PodcastEpisode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode One',
            'slug' => 'episode-one',
            'episode_number' => 1,
            'status' => 'active',
        ]);

        PodcastEpisode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode Two',
            'slug' => 'episode-two',
            'episode_number' => 2,
            'status' => 'active',
        ]);

        $episodes = $podcast->episodes;

        $this->assertSame(
            1,
            $episodes->first()->episode_number
        );

        $this->assertSame(
            2,
            $episodes->get(1)->episode_number
        );

        $this->assertSame(
            3,
            $episodes->get(2)->episode_number
        );
    }

    public function test_published_episodes_count_only_counts_active_published_episodes(): void
    {
        $podcast = Podcast::create([
            'title' => 'Doxa Palace Podcast',
            'slug' => 'doxa-palace-podcast',
            'status' => 'active',
        ]);

        PodcastEpisode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Published Episode',
            'slug' => 'published-episode',
            'episode_number' => 1,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        PodcastEpisode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Draft Episode',
            'slug' => 'draft-episode',
            'episode_number' => 2,
            'status' => 'draft',
        ]);

        PodcastEpisode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Future Episode',
            'slug' => 'future-episode',
            'episode_number' => 3,
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $this->assertSame(
            1,
            $podcast->publishedEpisodesCount()
        );
    }
}