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
        Schema::create('audiobook_chapters', function (Blueprint $table) {
            $table->id();

            /**
             * UUID used for public/API references.
             */
            $table->uuid('uuid')->unique();

            /**
             * Audiobook this chapter belongs to.
             */
            $table->foreignId('audiobook_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * Chapter/track information.
             */
            $table->string('title');

            $table->text('description')->nullable();

            /**
             * Position of the chapter in the audiobook.
             *
             * Example:
             * 1 = Introduction
             * 2 = Chapter One
             * 3 = Chapter Two
             */
            $table->unsignedInteger('track_number');

            /**
             * Audio file location.
             *
             * This should contain the private storage path,
             * not a publicly exposed URL.
             */
            $table->string('audio_file');

            /**
             * Duration stored in seconds.
             *
             * Example:
             * 600 = 10 minutes
             */
            $table->unsignedInteger('duration_seconds')->default(0);

            /**
             * Chapter availability.
             *
             * active   = available
             * inactive = temporarily unavailable
             */
            $table->string('status')->default('active');

            /**
             * Whether this chapter may be previewed
             * before purchasing the audiobook.
             */
            $table->boolean('is_preview')->default(false);

            /**
             * Optional publication timestamp.
             */
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            /**
             * Prevent duplicate chapter numbers
             * within the same audiobook.
             */
            $table->unique([
                'audiobook_id',
                'track_number',
            ]);

            /**
             * Useful for retrieving chapters
             * in playback order.
             */
            $table->index([
                'audiobook_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audiobook_chapters');
    }
};