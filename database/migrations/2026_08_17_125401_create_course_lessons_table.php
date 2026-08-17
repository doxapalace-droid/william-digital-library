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
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Course containing this lesson.
             */
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            /**
             * Video attached to this lesson.
             *
             * A lesson represents a structured course
             * lesson built around a video.
             */
            $table->foreignId('video_id')
                ->constrained('videos')
                ->restrictOnDelete();

            /**
             * Lesson title.
             */
            $table->string('title');

            /**
             * URL-friendly lesson identifier.
             */
            $table->string('slug');

            /**
             * Lesson description.
             */
            $table->text('description')->nullable();

            /**
             * Position of the lesson within the course.
             */
            $table->unsignedInteger('lesson_number');

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
             * Whether the lesson can be watched
             * without purchasing the course.
             */
            $table->boolean('is_preview')
                ->default(false);

            /**
             * Date and time the lesson becomes public.
             */
            $table->timestamp('published_at')
                ->nullable();

            $table->timestamps();

            /**
             * A course cannot contain two lessons
             * with the same lesson number.
             */
            $table->unique(
                ['course_id', 'lesson_number'],
                'course_lessons_course_number_unique'
            );

            /**
             * A lesson slug only needs to be unique
             * within its course.
             */
            $table->unique(
                ['course_id', 'slug'],
                'course_lessons_course_slug_unique'
            );

            /**
             * Useful indexes for course catalogue
             * and lesson access queries.
             */
            $table->index(
                ['course_id', 'status', 'published_at'],
                'course_lessons_course_status_published_index'
            );

            $table->index(
                ['video_id'],
                'course_lessons_video_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
    }
};