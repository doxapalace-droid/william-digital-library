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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Video title.
             */
            $table->string('title');

            /**
             * URL-friendly identifier.
             */
            $table->string('slug')->unique();

            /**
             * Video description.
             */
            $table->text('description')->nullable();

            /**
             * Cover/artwork image.
             */
            $table->string('cover_image')->nullable();

            /**
             * Private video file path.
             *
             * This should never be exposed
             * directly to customers.
             */
            $table->string('video_file')->nullable();

            /**
             * Selling price.
             */
            $table->decimal('price', 10, 2)->default(0);

            /**
             * Currency used for the video.
             */
            $table->string('currency', 3)->default('USD');

            /**
             * Video publication status.
             *
             * Supported values:
             *
             * draft
             * active
             * inactive
             */
            $table->string('status', 20)->default('draft');

            /**
             * Video duration in seconds.
             */
            $table->unsignedInteger('duration_seconds')
                ->default(0);

            /**
             * Date and time the video becomes available.
             */
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            /**
             * Useful index for catalogue queries.
             */
            $table->index(
                ['status', 'published_at'],
                'videos_status_published_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};