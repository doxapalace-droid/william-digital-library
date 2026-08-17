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
        Schema::create('course_entitlements', function (Blueprint $table) {
            $table->id();

            /**
             * Public UUID.
             */
            $table->uuid('uuid')->unique();

            /**
             * Customer who owns the entitlement.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /**
             * Course the customer is entitled to access.
             */
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            /**
             * How the entitlement was granted.
             *
             * Examples:
             * purchase
             * free
             * admin
             * gift
             * membership
             */
            $table->string('source', 30)
                ->default('purchase');

            /**
             * Whether the customer is permitted
             * to access the course.
             */
            $table->boolean('can_access')
                ->default(true);

            /**
             * Entitlement status.
             *
             * Supported values:
             * active
             * inactive
             */
            $table->string('status', 20)
                ->default('active');

            /**
             * Date and time access was granted.
             */
            $table->timestamp('granted_at')
                ->nullable();

            /**
             * Optional expiration date.
             *
             * NULL means the entitlement does not expire.
             */
            $table->timestamp('expires_at')
                ->nullable();

            /**
             * Date and time the entitlement
             * was revoked.
             */
            $table->timestamp('revoked_at')
                ->nullable();

            $table->timestamps();

            /**
             * A customer should not have multiple
             * active ownership records for the same course.
             *
             * The application will use firstOrCreate()
             * when granting an entitlement.
             */
            $table->unique(
                ['user_id', 'course_id'],
                'course_entitlements_user_course_unique'
            );

            /**
             * Useful indexes for access checks.
             */
            $table->index(
                ['user_id', 'status', 'can_access'],
                'course_entitlements_user_access_index'
            );

            $table->index(
                ['course_id', 'status'],
                'course_entitlements_course_status_index'
            );

            $table->index(
                ['expires_at'],
                'course_entitlements_expires_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_entitlements');
    }
};