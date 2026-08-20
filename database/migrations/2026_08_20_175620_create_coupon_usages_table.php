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
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Coupon that was redeemed.
             */
            $table->foreignId('coupon_id')
                ->constrained('coupons')
                ->cascadeOnDelete();

            /**
             * Customer who redeemed the coupon.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /**
             * Order on which the coupon was redeemed.
             *
             * Restrict deletion so the redemption history
             * cannot silently disappear when an order
             * is deleted.
             */
            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            /**
             * Snapshot of the discount applied.
             *
             * This protects historical order data even
             * if the coupon is later modified.
             */
            $table->decimal('discount_amount', 10, 2);

            /**
             * Snapshot of the coupon code used.
             */
            $table->string('coupon_code', 50);

            $table->timestamps();

            /**
             * A coupon should only be recorded once
             * against the same order.
             */
            $table->unique(
                ['coupon_id', 'order_id'],
                'coupon_usages_coupon_order_unique'
            );

            /**
             * Useful indexes for usage-limit checks.
             */
            $table->index(
                ['coupon_id', 'user_id'],
                'coupon_usages_coupon_user_index'
            );

            $table->index(
                'user_id',
                'coupon_usages_user_index'
            );

            $table->index(
                'order_id',
                'coupon_usages_order_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};