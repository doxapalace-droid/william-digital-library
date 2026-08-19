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
        /*
         * Some databases may already have the corrected column names
         * because the original create migration was corrected before
         * the migration history was replayed.
         *
         * Only rename the old columns when they actually exist.
         */

        if (
            Schema::hasColumn(
                'podcast_episode_progress',
                'progress_percentage'
            )
            && ! Schema::hasColumn(
                'podcast_episode_progress',
                'progress_percent'
            )
        ) {
            Schema::table(
                'podcast_episode_progress',
                function (Blueprint $table): void {
                    $table->renameColumn(
                        'progress_percentage',
                        'progress_percent'
                    );
                }
            );
        }

        if (
            Schema::hasColumn(
                'podcast_episode_progress',
                'completed'
            )
            && ! Schema::hasColumn(
                'podcast_episode_progress',
                'is_completed'
            )
        ) {
            Schema::table(
                'podcast_episode_progress',
                function (Blueprint $table): void {
                    $table->renameColumn(
                        'completed',
                        'is_completed'
                    );
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * Reverse only when the corrected columns exist and
         * the old columns do not.
         */

        if (
            Schema::hasColumn(
                'podcast_episode_progress',
                'progress_percent'
            )
            && ! Schema::hasColumn(
                'podcast_episode_progress',
                'progress_percentage'
            )
        ) {
            Schema::table(
                'podcast_episode_progress',
                function (Blueprint $table): void {
                    $table->renameColumn(
                        'progress_percent',
                        'progress_percentage'
                    );
                }
            );
        }

        if (
            Schema::hasColumn(
                'podcast_episode_progress',
                'is_completed'
            )
            && ! Schema::hasColumn(
                'podcast_episode_progress',
                'completed'
            )
        ) {
            Schema::table(
                'podcast_episode_progress',
                function (Blueprint $table): void {
                    $table->renameColumn(
                        'is_completed',
                        'completed'
                    );
                }
            );
        }
    }
};