<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Basic book information
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('author')->default('William K. Danquah');
            $table->string('isbn')->nullable()->unique();

            // Book media/files
            $table->string('cover_image')->nullable();
            $table->string('ebook_file')->nullable();

            // Commercial information
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            // Publishing controls
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            // Soft deletion
            $table->softDeletes();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};