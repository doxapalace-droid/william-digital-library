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
        Schema::table('cart_items', function (Blueprint $table) {
            /**
             * Bundle being purchased.
             *
             * Nullable because a cart item may represent
             * a book, audiobook, course, or bundle.
             */
            $table->foreignId('bundle_id')
                ->nullable()
                ->after('course_id')
                ->constrained('bundles')
                ->cascadeOnDelete();

            /**
             * Prevent the same bundle from appearing
             * twice in one customer's cart.
             */
            $table->unique(
                ['user_id', 'bundle_id'],
                'cart_items_user_bundle_unique'
            );

            /**
             * Useful bundle lookup index.
             */
            $table->index(
                'bundle_id',
                'cart_items_bundle_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(
                'cart_items_user_bundle_unique'
            );

            $table->dropIndex(
                'cart_items_bundle_index'
            );

            $table->dropForeign(
                ['bundle_id']
            );

            $table->dropColumn('bundle_id');
        });
    }
};