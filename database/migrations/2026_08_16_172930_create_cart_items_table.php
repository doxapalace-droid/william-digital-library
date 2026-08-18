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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Customer who owns this cart item.
             */
            $table->foreignId('user_id')
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
             * Nullable because the cart item may
             * represent an audiobook or course instead.
             */
            $table->foreignId('book_id')
                ->nullable()
                ->constrained('books')
                ->cascadeOnDelete();

            /**
             * Audiobook being purchased.
             *
             * Nullable because the cart item may
             * represent a book or course instead.
             */
            $table->foreignId('audiobook_id')
                ->nullable()
                ->constrained('audiobooks')
                ->cascadeOnDelete();

            /**
             * Course being purchased.
             *
             * Nullable because the cart item may
             * represent a book or audiobook instead.
             */
            $table->foreignId('course_id')
                ->nullable()
                ->constrained('courses')
                ->cascadeOnDelete();

            /**
             * Price captured when the product
             * was added to the cart.
             *
             * This protects the customer's cart
             * from later price changes.
             */
            $table->decimal('unit_price', 10, 2);

            /**
             * Currency used for this cart item.
             */
            $table->string('currency', 3);

            /**
             * Quantity.
             *
             * Digital products will normally have
             * a quantity of 1.
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
             * twice in one customer's cart.
             */
            $table->unique(
                ['user_id', 'book_id'],
                'cart_items_user_book_unique'
            );

            /**
             * Prevent the same audiobook from appearing
             * twice in one customer's cart.
             */
            $table->unique(
                ['user_id', 'audiobook_id'],
                'cart_items_user_audiobook_unique'
            );

            /**
             * Prevent the same course from appearing
             * twice in one customer's cart.
             */
            $table->unique(
                ['user_id', 'course_id'],
                'cart_items_user_course_unique'
            );

            /**
             * Useful indexes for cart queries.
             */
            $table->index(
                ['user_id', 'item_type'],
                'cart_items_user_type_index'
            );

            $table->index(
                'book_id',
                'cart_items_book_index'
            );

            $table->index(
                'audiobook_id',
                'cart_items_audiobook_index'
            );

            $table->index(
                'course_id',
                'cart_items_course_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};