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
        Schema::create('podcasts', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Podcast name.
             */
            $table->string('title');

            /**
             * URL-friendly identifier.
             */
            $table->string('slug')->unique();

            /**
             * Podcast description.
             */
            $table->text('description')->nullable();

            /**
             * Podcast cover/artwork.
             */
            $table->string('cover_image')->nullable();

            /**
             * Publication status.
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
             * Whether the podcast should appear
             * as a featured podcast.
             */
            $table->boolean('is_featured')
                ->default(false);

            /**
             * Date and time the podcast becomes
             * publicly available.
             */
            $table->timestamp('published_at')
                ->nullable();

            $table->timestamps();

            /**
             * Useful catalogue indexes.
             */
            $table->index(
                ['status', 'published_at'],
                'podcasts_status_published_index'
            );

            $table->index(
                ['is_featured', 'status'],
                'podcasts_featured_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('podcasts');
    }
};