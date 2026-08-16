<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AudiobookChapterResource;
use App\Models\Audiobook;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AudiobookChapterController extends Controller
{
    /**
     * Display the publicly available chapters
     * belonging to an audiobook.
     *
     * Only active and published chapters are returned.
     */
    public function index(
        Audiobook $audiobook
    ): AnonymousResourceCollection {
        $this->ensureAudiobookIsPubliclyAvailable(
            $audiobook
        );

        $chapters = $audiobook
            ->chapters()
            ->where('status', 'active')
            ->where(function ($query) {
                $query
                    ->whereNull('published_at')
                    ->orWhere(
                        'published_at',
                        '<=',
                        now()
                    );
            })
            ->orderBy('track_number')
            ->get();

        return AudiobookChapterResource::collection(
            $chapters
        );
    }

    /**
     * Ensure that the audiobook is publicly available.
     */
    private function ensureAudiobookIsPubliclyAvailable(
        Audiobook $audiobook
    ): void {
        if (! $audiobook->isActive()) {
            abort(
                404,
                'Audiobook not found.'
            );
        }
    }
}