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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Customer who owns the subscription.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /**
             * Membership plan.
             */
            $table->foreignId('membership_plan_id')
                ->constrained('membership_plans')
                ->restrictOnDelete();

            /**
             * Subscription lifecycle.
             *
             * pending
             * trialing
             * active
             * past_due
             * cancelled
             * expired
             */
            $table->string('status', 20)
                ->default('pending');

            /**
             * Price captured when subscription was created.
             */
            $table->decimal('amount', 10, 2);

            /**
             * Currency captured with the subscription.
             */
            $table->string('currency', 3);

            /**
             * Subscription start.
             */
            $table->timestamp('starts_at')->nullable();

            /**
             * Optional trial period.
             */
            $table->timestamp('trial_ends_at')->nullable();

            /**
             * Current billing period.
             */
            $table->timestamp('current_period_start')
                ->nullable();

            $table->timestamp('current_period_end')
                ->nullable();

            /**
             * Expected next billing date.
             */
            $table->timestamp('next_billing_at')
                ->nullable();

            /**
             * Cancellation timestamp.
             */
            $table->timestamp('cancelled_at')
                ->nullable();

            /**
             * Final expiration timestamp.
             */
            $table->timestamp('expires_at')
                ->nullable();

            /**
             * Payment gateway information.
             */
            $table->string('gateway', 50)
                ->nullable();

            $table->string('payment_reference')
                ->nullable();

            $table->timestamps();

            /**
             * Useful indexes.
             */
            $table->index(
                ['user_id', 'status'],
                'subscriptions_user_status_index'
            );

            $table->index(
                ['membership_plan_id', 'status'],
                'subscriptions_plan_status_index'
            );

            $table->index(
                'next_billing_at',
                'subscriptions_next_billing_index'
            );

            $table->index(
                'expires_at',
                'subscriptions_expires_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};