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
        Schema::table('order_items', function (Blueprint $table) {
            /**
             * Bundle being purchased.
             *
             * Nullable because an order item may represent
             * a book, audiobook, course, or bundle.
             */
            $table->foreignId('bundle_id')
                ->nullable()
                ->after('course_id')
                ->constrained('bundles')
                ->restrictOnDelete();

            /**
             * Prevent the same bundle from appearing
             * twice in the same order.
             */
            $table->unique(
                ['order_id', 'bundle_id'],
                'order_items_order_bundle_unique'
            );

            /**
             * Useful bundle lookup index.
             */
            $table->index(
                'bundle_id',
                'order_items_bundle_index'
            );
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique(
                'order_items_order_bundle_unique'
            );

            $table->dropIndex(
                'order_items_bundle_index'
            );

            $table->dropForeign(
                ['bundle_id']
            );

            $table->dropColumn('bundle_id');
        });
    }
};