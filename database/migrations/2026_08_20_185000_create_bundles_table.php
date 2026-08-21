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
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Bundle information.
             */
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();

            /**
             * Commercial information.
             *
             * The bundle has its own selling price,
             * independent of the individual products.
             */
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            /**
             * Publishing and availability.
             */
            $table->boolean('is_active')->default(true);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            /**
             * Useful catalogue indexes.
             */
            $table->index(
                ['is_active', 'is_published'],
                'bundles_active_published_index'
            );

            $table->index(
                'published_at',
                'bundles_published_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundles');
    }
};