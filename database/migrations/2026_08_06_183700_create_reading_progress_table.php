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
        Schema::create('reading_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('book_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------
            | Reader position
            |--------------------------------------------------------------
            |
            | current_page and total_pages support page-based documents
            | such as PDFs.
            |
            | location remains available for flexible reader positions,
            | including EPUB chapters, anchors, or other identifiers.
            |
            */
            $table->unsignedInteger('current_page')->nullable();

            $table->unsignedInteger('total_pages')->nullable();

            $table->string('location')->nullable();

            /*
            | Percentage completed: 0.00 - 100.00
            */
            $table->decimal('progress_percentage', 5, 2)->default(0);

            /*
            | When the customer last opened/read this book.
            */
            $table->timestamp('last_read_at')->nullable();

            $table->timestamps();

            /*
            | One progress record per user per book.
            */
            $table->unique(['user_id', 'book_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_progress');
    }
};