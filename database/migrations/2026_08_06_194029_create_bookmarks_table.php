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
            |--------------------------------------------------------------
            | Bookmark position
            |--------------------------------------------------------------
            |
            | current_page supports page-based books such as PDFs.
            | location gives us flexibility for other reader formats later.
            |
            */
            $table->unsignedInteger('current_page')->nullable();

            $table->string('location')->nullable();

            /*
            | Optional customer-created label or note.
            */
            $table->string('label')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            /*
            | Helps us efficiently retrieve a customer's
            | bookmarks for a particular book.
            */
            $table->index(['user_id', 'book_id']);
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