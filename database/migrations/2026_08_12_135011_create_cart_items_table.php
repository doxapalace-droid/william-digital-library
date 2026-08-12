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

            $table->uuid('uuid')->unique();

            /**
             * Customer who owns the cart item.
             */
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * Book being added to the cart.
             */
            $table->foreignId('book_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * Price snapshot at the time
             * the book was added.
             */
            $table->decimal('unit_price', 10, 2);

            /**
             * Currency used for the item.
             */
            $table->string('currency', 3);

            /**
             * Number of copies/items.
             *
             * Digital books will normally use 1,
             * but keeping quantity makes the
             * cart structure flexible.
             */
            $table->unsignedInteger('quantity')->default(1);

            /**
             * Item subtotal.
             */
            $table->decimal('subtotal', 10, 2);

            $table->timestamps();

            /**
             * A customer cannot have the same
             * book twice in the cart.
             */
            $table->unique([
                'user_id',
                'book_id',
            ]);
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