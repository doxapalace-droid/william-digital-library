<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add explicit free-product status to books,
     * audiobooks, and courses.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('is_free')
                ->default(false)
                ->after('price');

            $table->index('is_free');
        });

        Schema::table('audiobooks', function (Blueprint $table) {
            $table->boolean('is_free')
                ->default(false)
                ->after('price');

            $table->index('is_free');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_free')
                ->default(false)
                ->after('price');

            $table->index('is_free');
        });
    }

    /**
     * Remove explicit free-product status.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['is_free']);
            $table->dropColumn('is_free');
        });

        Schema::table('audiobooks', function (Blueprint $table) {
            $table->dropIndex(['is_free']);
            $table->dropColumn('is_free');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['is_free']);
            $table->dropColumn('is_free');
        });
    }
};