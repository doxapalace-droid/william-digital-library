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
        Schema::create('audiobooks', function (Blueprint $table) {
            $table->id();

            /**
             * UUID used for public/API references.
             */
            $table->uuid('uuid')->unique();

            /**
             * The book this audiobook belongs to.
             *
             * One book may have one audiobook edition.
             */
            $table->foreignId('book_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * Audiobook description.
             *
             * Nullable because the parent book may already
             * contain the main description.
             */
            $table->text('description')->nullable();

            /**
             * Audiobook artwork.
             *
             * If null, the application can fall back
             * to the book's cover image.
             */
            $table->string('cover_image')->nullable();

            /**
             * Commercial information.
             */
            $table->decimal('price', 10, 2)->default(0);

            $table->string('currency', 3)->default('USD');

            /**
             * Audiobook availability.
             *
             * draft     = not publicly available
             * active    = available for purchase
             * inactive  = temporarily unavailable
             */
            $table->string('status')->default('draft');

            /**
             * Total audiobook duration in seconds.
             *
             * This will be calculated from the chapters/tracks.
             */
            $table->unsignedInteger('duration_seconds')->default(0);

            /**
             * Publication timestamp.
             */
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            /**
             * A book can have only one audiobook edition
             * in the current architecture.
             */
            $table->unique('book_id');

            /**
             * Useful indexes for catalogue queries.
             */
            $table->index('status');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audiobooks');
    }
};