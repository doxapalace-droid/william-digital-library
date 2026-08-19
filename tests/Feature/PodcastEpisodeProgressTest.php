<?php

namespace Tests\Feature;

use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Models\PodcastEpisodeProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodcastEpisodeProgressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Progress record can be created.
     */
    public function test_progress_record_can_be_created(): void
    {
        $user = User::factory()->create();

        $podcast = Podcast::factory()->create();

        $episode = PodcastEpisode::factory()->create([
            'podcast_id' => $podcast->id,
        ]);

        $progress = PodcastEpisodeProgress::create([
            'user_id' => $user->id,
            'podcast_episode_id' => $episode->id,
            'position_seconds' => 300,
            'duration_seconds' => 1200,
            'progress_percent' => 25,
            'is_completed' => false,
            'last_played_at' => now(),
        ]);

        $this->assertDatabaseHas(
            'podcast_episode_progress',
            [
                'id' => $progress->id,
                'user_id' => $user->id,
                'podcast_episode_id' => $episode->id,
                'position_seconds' => 300,
            ]
        );
    }

    /**
     * Progress record has a UUID.
     */
    public function test_progress_has_uuid(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create();

        $this->assertNotNull($progress->uuid);
    }

    /**
     * Progress uses UUID for route model binding.
     */
    public function test_progress_uses_uuid_for_route_model_binding(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create();

        $this->assertSame(
            'uuid',
            $progress->getRouteKeyName()
        );
    }

    /**
     * Progress belongs to a user.
     */
    public function test_progress_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $progress = PodcastEpisodeProgress::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertTrue(
            $progress->user->is($user)
        );
    }

    /**
     * Progress belongs to a podcast episode.
     */
    public function test_progress_belongs_to_podcast_episode(): void
    {
        $episode = PodcastEpisode::factory()->create();

        $progress = PodcastEpisodeProgress::factory()->create([
            'podcast_episode_id' => $episode->id,
        ]);

        $this->assertTrue(
            $progress->podcastEpisode->is($episode)
        );
    }

    /**
     * User has many podcast episode progress records.
     */
    public function test_user_has_many_podcast_episode_progress_records(): void
    {
        $user = User::factory()->create();

        PodcastEpisodeProgress::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        $this->assertCount(
            3,
            $user->podcastEpisodeProgress
        );
    }

    /**
     * Podcast episode has many progress records.
     */
    public function test_podcast_episode_has_many_progress_records(): void
    {
        $episode = PodcastEpisode::factory()->create();

        PodcastEpisodeProgress::factory()->count(3)->create([
            'podcast_episode_id' => $episode->id,
        ]);

        $this->assertCount(
            3,
            $episode->progress
        );
    }

    /**
     * Position is cast to integer.
     */
    public function test_position_is_cast_to_integer(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'position_seconds' => '450',
        ]);

        $this->assertIsInt(
            $progress->position_seconds
        );
    }

    /**
     * Duration is cast to integer.
     */
    public function test_duration_is_cast_to_integer(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'duration_seconds' => '1800',
        ]);

        $this->assertIsInt(
            $progress->duration_seconds
        );
    }

    /**
     * Progress percentage is cast correctly.
     */
    public function test_progress_percentage_is_cast_correctly(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'progress_percent' => '42.50',
        ]);

        $this->assertSame(
            '42.50',
            $progress->progress_percent
        );
    }

    /**
     * Completion value is cast to boolean.
     */
    public function test_completed_value_is_cast_to_boolean(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'is_completed' => 1,
        ]);

        $this->assertIsBool(
            $progress->is_completed
        );

        $this->assertTrue(
            $progress->is_completed
        );
    }

    /**
     * Last played timestamp is cast to datetime.
     */
    public function test_last_played_at_is_cast_to_datetime(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'last_played_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $progress->last_played_at
        );
    }

    /**
     * hasStarted() detects playback.
     */
    public function test_has_started_detects_playback(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'position_seconds' => 120,
        ]);

        $this->assertTrue(
            $progress->hasStarted()
        );
    }

    /**
     * hasStarted() returns false before playback.
     */
    public function test_has_started_returns_false_before_playback(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'position_seconds' => 0,
        ]);

        $this->assertFalse(
            $progress->hasStarted()
        );
    }

    /**
     * isCompleted() detects completed episodes.
     */
    public function test_is_completed_detects_completed_episode(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'is_completed' => true,
        ]);

        $this->assertTrue(
            $progress->isCompleted()
        );
    }

    /**
     * isCompleted() returns false for incomplete episodes.
     */
    public function test_is_completed_returns_false_for_incomplete_episode(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'is_completed' => false,
        ]);

        $this->assertFalse(
            $progress->isCompleted()
        );
    }

    /**
     * Calculated progress percentage is correct.
     */
    public function test_calculated_progress_percentage_is_correct(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'position_seconds' => 300,
            'duration_seconds' => 1200,
        ]);

        $this->assertSame(
            25.0,
            $progress->calculatedProgressPercent()
        );
    }

    /**
     * Calculated progress percentage cannot exceed 100.
     */
    public function test_calculated_progress_percentage_cannot_exceed_100(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'position_seconds' => 1500,
            'duration_seconds' => 1200,
        ]);

        $this->assertSame(
            100.0,
            $progress->calculatedProgressPercent()
        );
    }

    /**
     * Zero duration produces zero progress.
     */
    public function test_zero_duration_produces_zero_progress(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'position_seconds' => 0,
            'duration_seconds' => 0,
        ]);

        $this->assertSame(
            0.0,
            $progress->calculatedProgressPercent()
        );
    }

    /**
     * Remaining playback time is calculated correctly.
     */
    public function test_remaining_seconds_are_calculated_correctly(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'position_seconds' => 300,
            'duration_seconds' => 1200,
        ]);

        $this->assertSame(
            900,
            $progress->remainingSeconds()
        );
    }

    /**
     * Remaining playback time cannot become negative.
     */
    public function test_remaining_seconds_cannot_be_negative(): void
    {
        $progress = PodcastEpisodeProgress::factory()->create([
            'position_seconds' => 1500,
            'duration_seconds' => 1200,
        ]);

        $this->assertSame(
            0,
            $progress->remainingSeconds()
        );
    }

    /**
     * One user cannot have duplicate progress
     * records for the same episode.
     */
    public function test_user_cannot_have_duplicate_progress_for_same_episode(): void
    {
        $user = User::factory()->create();

        $episode = PodcastEpisode::factory()->create();

        PodcastEpisodeProgress::factory()->create([
            'user_id' => $user->id,
            'podcast_episode_id' => $episode->id,
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        PodcastEpisodeProgress::factory()->create([
            'user_id' => $user->id,
            'podcast_episode_id' => $episode->id,
        ]);
    }

    /**
     * Different users can have progress for the same episode.
     */
    public function test_different_users_can_track_same_episode(): void
    {
        $userOne = User::factory()->create();

        $userTwo = User::factory()->create();

        $episode = PodcastEpisode::factory()->create();

        PodcastEpisodeProgress::factory()->create([
            'user_id' => $userOne->id,
            'podcast_episode_id' => $episode->id,
        ]);

        PodcastEpisodeProgress::factory()->create([
            'user_id' => $userTwo->id,
            'podcast_episode_id' => $episode->id,
        ]);

        $this->assertDatabaseCount(
            'podcast_episode_progress',
            2
        );
    }
}