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
        Schema::create('podcast_episodes', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Podcast this episode belongs to.
             */
            $table->foreignId('podcast_id')
                ->constrained('podcasts')
                ->cascadeOnDelete();

            /**
             * Episode title.
             */
            $table->string('title');

            /**
             * URL-friendly identifier.
             *
             * Unique only within a podcast.
             */
            $table->string('slug');

            /**
             * Episode description.
             */
            $table->text('description')->nullable();

            /**
             * Optional episode artwork.
             *
             * If NULL, the podcast cover can be used.
             */
            $table->string('cover_image')->nullable();

            /**
             * Private audio file path.
             *
             * This must never be exposed directly
             * through the public API.
             */
            $table->string('audio_file')->nullable();

            /**
             * Optional private video file path.
             *
             * This allows one episode to contain:
             *
             * - audio only
             * - video only
             * - both audio and video
             */
            $table->string('video_file')->nullable();

            /**
             * Episode duration in seconds.
             */
            $table->unsignedInteger('duration_seconds')
                ->default(0);

            /**
             * Episode publication status.
             *
             * Supported values:
             *
             * draft
             * active
             * inactive
             */
            $table->string('status', 20)
                ->default('draft');

            /**
             * Whether this episode is available
             * to everyone for free.
             *
             * Podcasts are intended to be a free
             * content area of the library.
             */
            $table->boolean('is_free')
                ->default(true);

            /**
             * Whether this episode should be
             * highlighted in the podcast catalogue.
             */
            $table->boolean('is_featured')
                ->default(false);

            /**
             * Episode number within the podcast.
             */
            $table->unsignedInteger('episode_number');

            /**
             * Date and time the episode becomes
             * publicly available.
             */
            $table->timestamp('published_at')
                ->nullable();

            $table->timestamps();

            /**
             * An episode number must be unique
             * within a podcast.
             */
            $table->unique(
                ['podcast_id', 'episode_number'],
                'podcast_episodes_podcast_number_unique'
            );

            /**
             * Episode slug only needs to be unique
             * within its podcast.
             */
            $table->unique(
                ['podcast_id', 'slug'],
                'podcast_episodes_podcast_slug_unique'
            );

            /**
             * Useful indexes for catalogue queries.
             */
            $table->index(
                ['podcast_id', 'status', 'published_at'],
                'podcast_episodes_podcast_status_published_index'
            );

            $table->index(
                ['is_featured', 'status'],
                'podcast_episodes_featured_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('podcast_episodes');
    }
};