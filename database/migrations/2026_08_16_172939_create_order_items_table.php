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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Parent order.
             */
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * Type of digital product.
             *
             * Supported values:
             *
             * book
             * audiobook
             * course
             */
            $table->string('item_type', 20);

            /**
             * Book being purchased.
             *
             * Nullable because the order item may
             * represent an audiobook or course instead.
             */
            $table->foreignId('book_id')
                ->nullable()
                ->constrained('books')
                ->restrictOnDelete();

            /**
             * Audiobook being purchased.
             *
             * Nullable because the order item may
             * represent a book or course instead.
             */
            $table->foreignId('audiobook_id')
                ->nullable()
                ->constrained('audiobooks')
                ->restrictOnDelete();

            /**
             * Course being purchased.
             *
             * Nullable because the order item may
             * represent a book or audiobook instead.
             */
            $table->foreignId('course_id')
                ->nullable()
                ->constrained('courses')
                ->restrictOnDelete();

            /**
             * Price captured at the time of purchase.
             */
            $table->decimal('unit_price', 10, 2);

            /**
             * Currency used for the item.
             */
            $table->string('currency', 3);

            /**
             * Quantity.
             *
             * Digital products will normally use 1.
             */
            $table->unsignedInteger('quantity')
                ->default(1);

            /**
             * Item subtotal.
             */
            $table->decimal('subtotal', 10, 2);

            $table->timestamps();

            /**
             * Prevent the same book from appearing
             * twice in the same order.
             */
            $table->unique(
                ['order_id', 'book_id'],
                'order_items_order_book_unique'
            );

            /**
             * Prevent the same audiobook from appearing
             * twice in the same order.
             */
            $table->unique(
                ['order_id', 'audiobook_id'],
                'order_items_order_audiobook_unique'
            );

            /**
             * Prevent the same course from appearing
             * twice in the same order.
             */
            $table->unique(
                ['order_id', 'course_id'],
                'order_items_order_course_unique'
            );

            /**
             * Useful indexes.
             */
            $table->index(
                ['order_id', 'item_type'],
                'order_items_order_type_index'
            );

            $table->index(
                'book_id',
                'order_items_book_index'
            );

            $table->index(
                'audiobook_id',
                'order_items_audiobook_index'
            );

            $table->index(
                'course_id',
                'order_items_course_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};