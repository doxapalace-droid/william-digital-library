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
        Schema::create('reading_notes', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Owner
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Book
            |--------------------------------------------------------------------------
            */

            $table->foreignId('book_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Note position
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
            | Note content
            |--------------------------------------------------------------------------
            */

            $table->text('note');

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Index
            |--------------------------------------------------------------------------
            |
            | Optimized for retrieving a user's notes for a specific
            | book and ordering/filtering them by page.
            |
            */

            $table->index([
                'user_id',
                'book_id',
                'current_page',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_notes');
    }
};