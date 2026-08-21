<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
         * PostgreSQL supports a native JSON column and requires
         * an explicit USING clause when converting existing text.
         *
         * SQLite does not support PostgreSQL's ALTER COLUMN syntax.
         * For SQLite, the existing text column is intentionally left
         * unchanged because Laravel's JSON cast handles serialization
         * and deserialization at the application level.
         */
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE payments
                 ALTER COLUMN gateway_response TYPE json
                 USING (
                     CASE
                         WHEN gateway_response IS NULL
                              OR BTRIM(gateway_response) = ''
                         THEN NULL
                         ELSE gateway_response::json
                     END
                 )"
            );

            return;
        }

        /*
         * SQLite does not have a native JSON type in the same sense
         * as PostgreSQL and does not support ALTER COLUMN TYPE.
         *
         * Leave gateway_response as TEXT. The Payment model casts
         * this attribute to an array, so JSON remains correctly
         * serialized and deserialized by Laravel.
         */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * Only PostgreSQL needs an explicit conversion back to TEXT.
         */
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE payments
                 ALTER COLUMN gateway_response TYPE text
                 USING gateway_response::text"
            );
        }

        /*
         * SQLite requires no action because its column remains TEXT.
         */
    }
};