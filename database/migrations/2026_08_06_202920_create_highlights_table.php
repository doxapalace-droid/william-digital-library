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
        Schema::create('highlights', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('book_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------
            | Highlight position
            |--------------------------------------------------------------
            |
            | current_page supports PDF/page-based books.
            | location allows the reader to store a more flexible position
            | for EPUB or future reader formats.
            |
            */
            $table->unsignedInteger('current_page')->nullable();
            $table->string('location')->nullable();

            /*
            | The actual text selected by the reader.
            */
            $table->text('selected_text');

            /*
            | Optional note attached to the highlighted passage.
            */
            $table->text('note')->nullable();

            /*
            | Allows the frontend to remember the reader's chosen
            | highlight style or colour.
            */
            $table->string('color', 30)->default('yellow');

            $table->timestamps();

            /*
            | Helpful indexes when retrieving a user's highlights
            | for a particular book.
            */
            $table->index(['user_id', 'book_id']);
            $table->index(['book_id', 'current_page']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('highlights');
    }
};