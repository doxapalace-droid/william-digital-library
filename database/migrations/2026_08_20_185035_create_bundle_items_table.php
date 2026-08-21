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
        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();

            /**
             * Bundle containing this product.
             */
            $table->foreignId('bundle_id')
                ->constrained('bundles')
                ->cascadeOnDelete();

            /**
             * Type of product contained in the bundle.
             *
             * Supported values:
             *
             * book
             * audiobook
             * course
             * video
             */
            $table->string('item_type', 20);

            /**
             * Product references.
             *
             * Only one of these should be populated
             * for each bundle item.
             */
            $table->foreignId('book_id')
                ->nullable()
                ->constrained('books')
                ->restrictOnDelete();

            $table->foreignId('audiobook_id')
                ->nullable()
                ->constrained('audiobooks')
                ->restrictOnDelete();

            $table->foreignId('course_id')
                ->nullable()
                ->constrained('courses')
                ->restrictOnDelete();

            $table->foreignId('video_id')
                ->nullable()
                ->constrained('videos')
                ->restrictOnDelete();

            $table->timestamps();

            /**
             * Prevent the same book from appearing
             * twice in one bundle.
             */
            $table->unique(
                ['bundle_id', 'book_id'],
                'bundle_items_bundle_book_unique'
            );

            /**
             * Prevent the same audiobook from appearing
             * twice in one bundle.
             */
            $table->unique(
                ['bundle_id', 'audiobook_id'],
                'bundle_items_bundle_audiobook_unique'
            );

            /**
             * Prevent the same course from appearing
             * twice in one bundle.
             */
            $table->unique(
                ['bundle_id', 'course_id'],
                'bundle_items_bundle_course_unique'
            );

            /**
             * Prevent the same video from appearing
             * twice in one bundle.
             */
            $table->unique(
                ['bundle_id', 'video_id'],
                'bundle_items_bundle_video_unique'
            );

            /**
             * Useful catalogue queries.
             */
            $table->index(
                ['bundle_id', 'item_type'],
                'bundle_items_bundle_type_index'
            );

            $table->index(
                'book_id',
                'bundle_items_book_index'
            );

            $table->index(
                'audiobook_id',
                'bundle_items_audiobook_index'
            );

            $table->index(
                'course_id',
                'bundle_items_course_index'
            );

            $table->index(
                'video_id',
                'bundle_items_video_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
    }
};