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
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Basic plan information.
             */
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            /**
             * Commercial information.
             */
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            /**
             * Billing interval.
             *
             * Supported values:
             *
             * month
             * year
             */
            $table->string('billing_interval', 20);

            /**
             * Number of billing intervals.
             */
            $table->unsignedInteger('billing_interval_count')
                ->default(1);

            /**
             * Optional free trial period.
             *
             * NULL or 0 means no trial.
             */
            $table->unsignedInteger('trial_days')
                ->nullable();

            /**
             * Plan availability.
             */
            $table->boolean('is_active')->default(true);
            $table->boolean('is_published')->default(false);

            /**
             * Publication timestamp.
             */
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            /**
             * Useful catalogue indexes.
             */
            $table->index(
                ['is_active', 'is_published'],
                'membership_plans_active_published_index'
            );

            $table->index(
                'billing_interval',
                'membership_plans_billing_interval_index'
            );

            $table->index(
                'published_at',
                'membership_plans_published_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};