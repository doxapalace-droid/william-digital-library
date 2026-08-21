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
        Schema::table('bundle_items', function (Blueprint $table) {
            /**
             * Public UUID used for API and route references.
             */
            $table->uuid('uuid')
                ->unique()
                ->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bundle_items', function (Blueprint $table) {
            $table->dropUnique([
                'uuid',
            ]);

            $table->dropColumn('uuid');
        });
    }
};