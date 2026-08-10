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
        Schema::table('books', function (Blueprint $table) {

            $table->decimal('average_rating', 3, 2)
                ->default(0)
                ->after('cover_image');

            $table->unsignedInteger('reviews_count')
                ->default(0)
                ->after('average_rating');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {

            $table->dropColumn([
                'average_rating',
                'reviews_count',
            ]);

        });
    }
};