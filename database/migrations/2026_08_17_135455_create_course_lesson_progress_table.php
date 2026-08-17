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
        Schema::create('course_lesson_progress', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('course_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('course_lesson_id')
                ->constrained('course_lessons')
                ->cascadeOnDelete();

            $table->unsignedInteger('position_seconds')
                ->default(0);

            $table->boolean('completed')
                ->default(false);

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamp('last_accessed_at')
                ->nullable();

            $table->timestamps();

            /**
             * A user can have only one progress record
             * for a particular lesson.
             */
            $table->unique([
                'user_id',
                'course_lesson_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_lesson_progress');
    }
};