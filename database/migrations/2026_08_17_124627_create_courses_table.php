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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Course title.
             */
            $table->string('title');

            /**
             * URL-friendly identifier.
             */
            $table->string('slug')->unique();

            /**
             * Optional course subtitle.
             */
            $table->string('subtitle')->nullable();

            /**
             * Course description.
             */
            $table->text('description')->nullable();

            /**
             * Public course cover image.
             */
            $table->string('cover_image')->nullable();

            /**
             * Course price.
             *
             * A value of 0 represents a free course.
             */
            $table->decimal('price', 10, 2)->default(0);

            /**
             * Currency used for the course.
             */
            $table->string('currency', 3)->default('USD');

            /**
             * Publication status.
             *
             * Supported values:
             *
             * draft
             * active
             * inactive
             */
            $table->string('status', 20)->default('draft');

            /**
             * Date and time the course becomes public.
             *
             * Nullable means the course can be
             * immediately available once active.
             */
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            /**
             * Useful catalogue indexes.
             */
            $table->index(
                ['status', 'published_at'],
                'courses_status_published_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};