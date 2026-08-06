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
    { Schema::create('book_entitlements', function (Blueprint $table) {
        $table->id();
        $table->uuid('uuid')->unique();

        // Who owns the entitlement
        $table->foreignId('user_id')
            ->constrained()
            ->cascadeOnDelete();

        // Which book they can access
        $table->foreignId('book_id')
            ->constrained()
            ->cascadeOnDelete();

        // How access was obtained
        $table->string('source')->default('purchase');

        // Digital access controls
        $table->boolean('can_read')->default(true);
        $table->boolean('can_download')->default(false);

        // Entitlement status
        $table->string('status')->default('active');

        // Access period
        $table->timestamp('granted_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamp('revoked_at')->nullable();

        $table->timestamps();

        // Prevent duplicate ownership records
        $table->unique(['user_id', 'book_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_entitlements');
    }
};
