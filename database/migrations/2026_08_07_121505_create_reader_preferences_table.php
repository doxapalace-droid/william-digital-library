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
        Schema::create('reader_preferences', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | User
            |--------------------------------------------------------------------------
            |
            | Each preference record belongs to one authenticated user.
            |
            */
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Reader Appearance
            |--------------------------------------------------------------------------
            |
            | These settings allow each customer to personalize
            | their digital reading experience.
            |
            */
            $table->string('theme')->default('light');

            $table->unsignedTinyInteger('font_size')
                ->default(18);

            $table->string('font_family')
                ->default('serif');

            $table->decimal('line_spacing', 3, 2)
                ->default(1.60);

            /*
            |--------------------------------------------------------------------------
            | Reader Layout
            |--------------------------------------------------------------------------
            */
            $table->string('reading_mode')
                ->default('paginated');

            /*
            |--------------------------------------------------------------------------
            | Constraints
            |--------------------------------------------------------------------------
            |
            | Each customer can have only one reader preference record.
            |
            */
            $table->unique('user_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reader_preferences');
    }
};