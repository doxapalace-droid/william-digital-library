<?php

namespace Tests\Feature;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A video can be created.
     */
    public function test_video_can_be_created(): void
    {
        $video = Video::factory()->create();

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'uuid' => $video->uuid,
            'title' => $video->title,
            'slug' => $video->slug,
        ]);
    }

    /**
     * A video has a UUID.
     */
    public function test_video_has_uuid(): void
    {
        $video = Video::factory()->create();

        $this->assertNotNull($video->uuid);
        $this->assertIsString($video->uuid);
    }

    /**
     * UUID is used for route model binding.
     */
    public function test_video_uses_uuid_for_route_binding(): void
    {
        $video = Video::factory()->create();

        $this->assertSame(
            'uuid',
            $video->getRouteKeyName()
        );
    }

    /**
     * Video price is cast correctly.
     */
    public function test_video_price_is_cast_to_decimal(): void
    {
        $video = Video::factory()->create([
            'price' => 49.99,
        ]);

        $this->assertSame(
            '49.99',
            $video->price
        );
    }

    /**
     * Video duration is cast to an integer.
     */
    public function test_video_duration_is_cast_to_integer(): void
    {
        $video = Video::factory()->create([
            'duration_seconds' => 3600,
        ]);

        $this->assertIsInt(
            $video->duration_seconds
        );

        $this->assertSame(
            3600,
            $video->duration_seconds
        );
    }

    /**
     * Published date is cast to a datetime.
     */
    public function test_video_published_at_is_cast_to_datetime(): void
    {
        $video = Video::factory()->create([
            'published_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $video->published_at
        );
    }

    /**
     * An active published video is active.
     */
    public function test_active_published_video_is_active(): void
    {
        $video = Video::factory()
            ->create([
                'status' => 'active',
                'published_at' => now()->subDay(),
            ]);

        $this->assertTrue(
            $video->isActive()
        );
    }

    /**
     * A draft video is not active.
     */
    public function test_draft_video_is_not_active(): void
    {
        $video = Video::factory()
            ->draft()
            ->create();

        $this->assertFalse(
            $video->isActive()
        );
    }

    /**
     * An inactive video is not active.
     */
    public function test_inactive_video_is_not_active(): void
    {
        $video = Video::factory()
            ->inactive()
            ->create();

        $this->assertFalse(
            $video->isActive()
        );
    }

    /**
     * A future-published video is not active.
     */
    public function test_future_video_is_not_active(): void
    {
        $video = Video::factory()
            ->future()
            ->create();

        $this->assertFalse(
            $video->isActive()
        );
    }

    /**
     * An active video is purchasable.
     */
    public function test_active_video_is_purchasable(): void
    {
        $video = Video::factory()->create([
            'status' => 'active',
            'published_at' => now()->subDay(),
            'price' => 25.00,
        ]);

        $this->assertTrue(
            $video->isPurchasable()
        );
    }

    /**
     * A draft video is not purchasable.
     */
    public function test_draft_video_is_not_purchasable(): void
    {
        $video = Video::factory()
            ->draft()
            ->create();

        $this->assertFalse(
            $video->isPurchasable()
        );
    }

    /**
     * A future video is not purchasable.
     */
    public function test_future_video_is_not_purchasable(): void
    {
        $video = Video::factory()
            ->future()
            ->create();

        $this->assertFalse(
            $video->isPurchasable()
        );
    }

    /**
     * A free active video is purchasable.
     */
    public function test_free_active_video_is_purchasable(): void
    {
        $video = Video::factory()
            ->free()
            ->create([
                'status' => 'active',
                'published_at' => now()->subDay(),
            ]);

        $this->assertTrue(
            $video->isPurchasable()
        );
    }

    /**
     * Video duration can be converted to minutes.
     */
    public function test_video_duration_in_minutes(): void
    {
        $video = Video::factory()->create([
            'duration_seconds' => 3600,
        ]);

        $this->assertSame(
            60.0,
            $video->durationInMinutes()
        );
    }

    /**
     * A thirty-minute video returns the correct duration.
     */
    public function test_video_duration_handles_partial_minutes(): void
    {
        $video = Video::factory()->create([
            'duration_seconds' => 1850,
        ]);

        $this->assertSame(
            30.83,
            $video->durationInMinutes()
        );
    }
}