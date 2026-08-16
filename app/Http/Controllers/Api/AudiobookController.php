<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AudiobookResource;
use App\Models\Audiobook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AudiobookController extends Controller
{
    /**
     * Display the public audiobook catalogue.
     *
     * Only active and currently published audiobooks
     * are returned.
     */
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $perPage = min(
            max(
                (int) $request->input('per_page', 12),
                1
            ),
            50
        );

        $audiobooks = Audiobook::query()
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
            ->with([
                'book.authors',
            ])
            ->withCount([
                'chapters' => function ($query) {
                    $query
                        ->where('status', 'active')
                        ->where(function ($chapterQuery) {
                            $chapterQuery
                                ->whereNull('published_at')
                                ->orWhere(
                                    'published_at',
                                    '<=',
                                    now()
                                );
                        });
                },
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return AudiobookResource::collection(
            $audiobooks
        );
    }

    /**
     * Display a single public audiobook.
     *
     * Only active and currently published audiobooks
     * are publicly accessible.
     */
    public function show(
        Audiobook $audiobook
    ): AudiobookResource {
        $this->ensurePubliclyAvailable($audiobook);

        $audiobook->load([
            'book.authors',
        ]);

        $audiobook->loadCount([
            'chapters' => function ($query) {
                $query
                    ->where('status', 'active')
                    ->where(function ($chapterQuery) {
                        $chapterQuery
                            ->whereNull('published_at')
                            ->orWhere(
                                'published_at',
                                '<=',
                                now()
                            );
                    });
            },
        ]);

        return new AudiobookResource(
            $audiobook
        );
    }

    /**
     * Ensure that an audiobook is publicly available.
     */
    private function ensurePubliclyAvailable(
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