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

            $table->uuid('uuid')->unique();

            /**
             * Parent order.
             */
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * Book being purchased.
             */
            $table->foreignId('book_id')
                ->constrained()
                ->restrictOnDelete();

            /**
             * Price snapshot.
             */
            $table->decimal('unit_price', 10, 2);

            $table->string('currency', 3);

            $table->unsignedInteger('quantity')->default(1);

            $table->decimal('subtotal', 10, 2);

            $table->timestamps();

            /**
             * A book can only occur once in a particular order.
             */
            $table->unique([
                'order_id',
                'book_id',
            ]);
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