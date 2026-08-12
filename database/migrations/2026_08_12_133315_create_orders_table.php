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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            /**
             * Customer who placed the order.
             */
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * Human-readable order number.
             *
             * Example:
             * DP-000001
             */
            $table->string('order_number')->unique();

            /**
             * Order lifecycle.
             *
             * pending
             * processing
             * completed
             * cancelled
             * failed
             */
            $table->string('status')->default('pending');

            /**
             * Payment lifecycle.
             *
             * unpaid
             * pending
             * paid
             * failed
             * refunded
             */
            $table->string('payment_status')->default('unpaid');

            /**
             * Currency used for this order.
             */
            $table->string('currency', 3)->default('USD');

            /**
             * Financial snapshot.
             */
            $table->decimal('subtotal', 10, 2)->default(0);

            $table->decimal('discount', 10, 2)->default(0);

            $table->decimal('total', 10, 2)->default(0);

            /**
             * Payment completion timestamp.
             */
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};