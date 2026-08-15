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
        Schema::create('audiobook_listening_progress', function (Blueprint $table) {
            $table->id();

            /**
             * UUID used for public/API references.
             */
            $table->uuid('uuid')->unique();

            /**
             * Customer listening to the audiobook.
             */
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * Audiobook being listened to.
             */
            $table->foreignId('audiobook_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * Current chapter/track.
             *
             * Nullable because the customer may have
             * opened the audiobook without starting playback.
             */
            $table->foreignId('audiobook_chapter_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /**
             * Current playback position within the chapter,
             * measured in seconds.
             *
             * Example:
             * 125 = 2 minutes 5 seconds.
             */
            $table->unsignedInteger('position_seconds')->default(0);

            /**
             * Total number of seconds the customer
             * has listened to.
             *
             * This is useful for analytics and progress tracking.
             */
            $table->unsignedBigInteger('listened_seconds')->default(0);

            /**
             * Percentage of the audiobook completed.
             *
             * Stored as a decimal for accuracy.
             */
            $table->decimal('progress_percent', 5, 2)->default(0);

            /**
             * Whether the customer has completed
             * the entire audiobook.
             */
            $table->boolean('is_completed')->default(false);

            /**
             * Last time the customer listened.
             */
            $table->timestamp('last_listened_at')->nullable();

            $table->timestamps();

            /**
             * One progress record per customer per audiobook.
             */
            $table->unique([
                'user_id',
                'audiobook_id',
            ]);

            /**
             * Useful indexes for progress queries.
             */
            $table->index([
                'user_id',
                'last_listened_at',
            ]);

            $table->index([
                'audiobook_id',
                'is_completed',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audiobook_listening_progress');
    }
};