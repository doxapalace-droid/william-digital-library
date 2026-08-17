<?php

namespace Tests\Feature;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Public video catalogue returns active videos.
     */
    public function test_public_video_catalogue_returns_active_videos(): void
    {
        $video = Video::factory()->create([
            'title' => 'Kingdom Leadership',
            'slug' => 'kingdom-leadership',
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/videos');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.uuid',
                $video->uuid
            )
            ->assertJsonPath(
                'data.0.title',
                'Kingdom Leadership'
            )
            ->assertJsonPath(
                'data.0.slug',
                'kingdom-leadership'
            );
    }

    /**
     * Draft videos do not appear in the public catalogue.
     */
    public function test_draft_video_does_not_appear_in_catalogue(): void
    {
        Video::factory()
            ->draft()
            ->create([
                'title' => 'Draft Video',
            ]);

        $response = $this->getJson('/api/videos');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Inactive videos do not appear in the public catalogue.
     */
    public function test_inactive_video_does_not_appear_in_catalogue(): void
    {
        Video::factory()
            ->inactive()
            ->create([
                'title' => 'Inactive Video',
            ]);

        $response = $this->getJson('/api/videos');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Future-published videos do not appear in the public catalogue.
     */
    public function test_future_video_does_not_appear_in_catalogue(): void
    {
        Video::factory()
            ->future()
            ->create([
                'title' => 'Future Video',
            ]);

        $response = $this->getJson('/api/videos');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * A video with no publication date can be publicly listed
     * when its status is active.
     */
    public function test_active_video_without_publication_date_is_visible(): void
    {
        $video = Video::factory()->create([
            'title' => 'Available Video',
            'status' => 'active',
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/videos');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.uuid',
                $video->uuid
            );
    }

    /**
     * Public video catalogue is paginated.
     */
    public function test_video_catalogue_is_paginated(): void
    {
        Video::factory()
            ->count(15)
            ->create([
                'status' => 'active',
                'published_at' => now()->subDay(),
            ]);

        $response = $this->getJson(
            '/api/videos?per_page=10'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.per_page',
                10
            )
            ->assertJsonPath(
                'meta.total',
                15
            );

        $this->assertCount(
            10,
            $response->json('data')
        );
    }

    /**
     * Per-page value cannot exceed the maximum of 50.
     */
    public function test_video_catalogue_limits_per_page_to_fifty(): void
    {
        Video::factory()
            ->count(3)
            ->create([
                'status' => 'active',
                'published_at' => now()->subDay(),
            ]);

        $response = $this->getJson(
            '/api/videos?per_page=100'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.per_page',
                50
            );
    }

    /**
     * Public video details can be retrieved by UUID.
     */
    public function test_public_video_details_are_available(): void
    {
        $video = Video::factory()->create([
            'title' => 'Kingdom Influence',
            'slug' => 'kingdom-influence',
            'description' => 'Understanding kingdom influence.',
            'status' => 'active',
            'published_at' => now()->subDay(),
            'price' => 25.00,
            'currency' => 'USD',
            'duration_seconds' => 3600,
        ]);

        $response = $this->getJson(
            "/api/videos/{$video->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $video->uuid
            )
            ->assertJsonPath(
                'data.title',
                'Kingdom Influence'
            )
            ->assertJsonPath(
                'data.slug',
                'kingdom-influence'
            )
            ->assertJsonPath(
                'data.description',
                'Understanding kingdom influence.'
            )
            ->assertJsonPath(
                'data.price',
                '25.00'
            )
            ->assertJsonPath(
                'data.currency',
                'USD'
            )
            ->assertJsonPath(
                'data.duration_seconds',
                3600
            )
            ->assertJsonPath(
                'data.duration_minutes',
                60
            );
    }

    /**
     * Draft video details are not publicly accessible.
     */
    public function test_draft_video_details_return_not_found(): void
    {
        $video = Video::factory()
            ->draft()
            ->create();

        $response = $this->getJson(
            "/api/videos/{$video->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Inactive video details are not publicly accessible.
     */
    public function test_inactive_video_details_return_not_found(): void
    {
        $video = Video::factory()
            ->inactive()
            ->create();

        $response = $this->getJson(
            "/api/videos/{$video->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Future-published video details are not publicly accessible.
     */
    public function test_future_video_details_return_not_found(): void
    {
        $video = Video::factory()
            ->future()
            ->create();

        $response = $this->getJson(
            "/api/videos/{$video->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * A nonexistent video returns 404.
     */
    public function test_nonexistent_video_returns_not_found(): void
    {
        $response = $this->getJson(
            '/api/videos/00000000-0000-0000-0000-000000000000'
        );

        $response->assertNotFound();
    }

    /**
     * Public video response includes cover image.
     */
    public function test_video_response_includes_cover_image(): void
    {
        $video = Video::factory()->create([
            'cover_image' => 'videos/covers/example.jpg',
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/videos/{$video->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.cover_image',
                'videos/covers/example.jpg'
            );
    }

    /**
     * Public video response does not expose private video files.
     */
    public function test_public_video_response_does_not_expose_private_video_file(): void
    {
        $video = Video::factory()->create([
            'video_file' => 'private/videos/example.mp4',
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/videos/{$video->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonMissingPath(
                'data.video_file'
            )
            ->assertJsonMissingPath(
                'data.video_path'
            );
    }

    /**
     * Public video response includes duration information.
     */
    public function test_public_video_response_includes_duration_information(): void
    {
        $video = Video::factory()->create([
            'duration_seconds' => 1850,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/videos/{$video->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.duration_seconds',
                1850
            )
            ->assertJsonPath(
                'data.duration_minutes',
                30.83
            );
    }

    /**
     * Public video catalogue does not require authentication.
     */
    public function test_guest_can_view_video_catalogue(): void
    {
        Video::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            '/api/videos'
        );

        $response->assertOk();
    }
}