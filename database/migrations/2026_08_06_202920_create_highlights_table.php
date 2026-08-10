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

    $table->uuid()->unique();

    $table->foreignId('user_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('book_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->unsignedInteger('current_page')->nullable();

    $table->string('location')->nullable();

    $table->text('selected_text');

    $table->text('note')->nullable();

    $table->string('color', 30)->default('yellow');

    $table->timestamps();

    $table->softDeletes();

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