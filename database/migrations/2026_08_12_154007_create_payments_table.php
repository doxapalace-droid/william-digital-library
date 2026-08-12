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
        Schema::create('payments', function (Blueprint $table) {
        $table->id();

        $table->uuid('uuid')->unique();

        $table->foreignId('order_id')
        ->constrained()
        ->cascadeOnDelete();

        $table->foreignId('user_id')
        ->constrained()
        ->cascadeOnDelete();

     /*
     * Payment gateway used.
     *
     * Example:
     * paystack
     * flutterwave
     */
        $table->string('gateway');

        /*
     * Gateway transaction/reference ID.
     */
        $table->string('transaction_reference')->nullable()->unique();

        /*
     * Payment lifecycle.
     *
     * pending
     * successful
     * failed
     * cancelled
     * refunded
     */
     $table->string('status')->default('pending');

        $table->string('currency', 3)->default('USD');

        /*
     * Amount expected to be paid.
     */
        $table->decimal('amount', 10, 2);

     /*
     * Gateway response/reference data.
      */
     $table->text('gateway_response')->nullable();

        /*
     * Payment timestamps.
     */
     $table->timestamp('paid_at')->nullable();

     $table->timestamp('failed_at')->nullable();

     $table->timestamps();
     
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
