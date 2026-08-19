<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('podcast_episode_progress', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Public UUID
            |--------------------------------------------------------------------------
            */

            $table->uuid('uuid')->unique();

            /*
            |--------------------------------------------------------------------------
            | Ownership
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('podcast_episode_id')
                ->constrained('podcast_episodes')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Playback Position
            |--------------------------------------------------------------------------
            |
            | Position and duration are stored in seconds.
            |
            */

            $table->unsignedInteger('position_seconds')
                ->default(0);

            $table->unsignedInteger('duration_seconds')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Progress
            |--------------------------------------------------------------------------
            |
            | Keep the established application field name:
            | progress_percent
            |
            */

            $table->decimal('progress_percent', 5, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Completion
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_completed')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Playback Activity
            |--------------------------------------------------------------------------
            */

            $table->timestamp('last_played_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | One Progress Record Per User / Episode
            |--------------------------------------------------------------------------
            */

            $table->unique(
                ['user_id', 'podcast_episode_id'],
                'podcast_progress_user_episode_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Query Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['user_id', 'last_played_at'],
                'podcast_progress_user_last_played_index'
            );

            $table->index(
                ['user_id', 'is_completed'],
                'podcast_progress_user_completed_index'
            );

            $table->index(
                ['podcast_episode_id'],
                'podcast_progress_episode_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('podcast_episode_progress');
    }
};