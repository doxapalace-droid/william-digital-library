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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Coupon code entered by the customer.
             *
             * Stored in uppercase so coupon matching
             * remains consistent.
             */
            $table->string('code', 50)->unique();

            /**
             * Optional internal/admin description.
             */
            $table->string('name')->nullable();

            $table->text('description')->nullable();

            /**
             * Discount type.
             *
             * percentage = percentage discount
             * fixed      = fixed monetary discount
             */
            $table->string('discount_type', 20);

            /**
             * Discount value.
             *
             * Examples:
             *
             * percentage: 10.00 = 10%
             * fixed:      5.00  = $5
             */
            $table->decimal('discount_value', 10, 2);

            /**
             * Maximum discount allowed for
             * percentage coupons.
             *
             * NULL means no maximum.
             */
            $table->decimal('maximum_discount', 10, 2)
                ->nullable();

            /**
             * Minimum order subtotal required
             * before the coupon can be applied.
             *
             * NULL means no minimum.
             */
            $table->decimal('minimum_order_amount', 10, 2)
                ->nullable();

            /**
             * Coupon availability.
             */
            $table->boolean('is_active')->default(true);

            /**
             * Optional validity period.
             *
             * NULL start = available immediately.
             * NULL end   = never expires.
             */
            $table->timestamp('starts_at')->nullable();

            $table->timestamp('expires_at')->nullable();

            /**
             * Total number of times the coupon
             * can be redeemed.
             *
             * NULL = unlimited.
             */
            $table->unsignedInteger('usage_limit')
                ->nullable();

            /**
             * Maximum number of times one customer
             * can use the coupon.
             *
             * NULL = unlimited.
             */
            $table->unsignedInteger('per_user_limit')
                ->nullable();

            /**
             * Number of successful redemptions.
             *
             * Maintained by the application.
             */
            $table->unsignedInteger('usage_count')
                ->default(0);

            /**
             * Product restrictions.
             *
             * NULL means the coupon applies to
             * all supported products.
             *
             * Examples:
             *
             * book
             * audiobook
             * course
             */
            $table->string('product_type', 20)
                ->nullable();

            $table->timestamps();

            /**
             * Useful indexes.
             */
            $table->index(
                ['is_active', 'starts_at', 'expires_at'],
                'coupons_availability_index'
            );

            $table->index(
                'product_type',
                'coupons_product_type_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};