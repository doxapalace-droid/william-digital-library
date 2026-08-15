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
        Schema::create('audiobook_entitlements', function (Blueprint $table) {
            $table->id();

            /**
             * UUID used for public/API references.
             */
            $table->uuid('uuid')->unique();

            /**
             * The customer who owns the audiobook entitlement.
             */
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * The audiobook the customer can access.
             */
            $table->foreignId('audiobook_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * How access was obtained.
             *
             * Examples:
             * purchase
             * admin
             * gift
             * promotion
             * free
             */
            $table->string('source')->default('purchase');

            /**
             * Audiobook access permissions.
             */
            $table->boolean('can_stream')->default(true);
            $table->boolean('can_download')->default(false);

            /**
             * Entitlement status.
             *
             * active   = access is currently available
             * revoked  = access has been removed
             * expired  = access period has ended
             * inactive = temporarily disabled
             */
            $table->string('status')->default('active');

            /**
             * Access period.
             */
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            /**
             * Prevent duplicate audiobook ownership records
             * for the same customer.
             */
            $table->unique([
                'user_id',
                'audiobook_id',
            ]);

            /**
             * Useful indexes for access checks.
             */
            $table->index([
                'user_id',
                'status',
            ]);

            $table->index([
                'audiobook_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audiobook_entitlements');
    }
};