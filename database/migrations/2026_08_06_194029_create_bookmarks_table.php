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
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('book_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Bookmark position
            |--------------------------------------------------------------------------
            |
            | current_page supports PDF/page-based books.
            | location supports EPUB and future reader formats.
            |
            */

            $table->unsignedInteger('current_page')->nullable();

            $table->string('location', 1000)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional bookmark label.
            |--------------------------------------------------------------------------
            */

            $table->string('label')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional note attached to the bookmark.
            |--------------------------------------------------------------------------
            */

            $table->text('note')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(['user_id', 'book_id']);

            $table->index(['book_id', 'current_page']);

            // Uncomment if duplicate bookmarks should not be allowed.
            // $table->unique([
            //     'user_id',
            //     'book_id',
            //     'current_page',
            //     'location',
            // ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};