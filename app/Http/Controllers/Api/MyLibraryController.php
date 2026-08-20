<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyLibraryController extends Controller
{
    /**
     * Display the authenticated user's digital library.
     *
     * Response structure:
     *
     * data:
     *     Owned books.
     *
     * audiobooks:
     *     Owned audiobooks.
     *
     * courses:
     *     Owned courses.
     *
     * Only products for which the authenticated customer
     * has an active entitlement are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
         * ---------------------------------------------------------
         * OWNED BOOKS
         * ---------------------------------------------------------
         *
         * A book appears in the library only when:
         *
         * 1. The book is published.
         * 2. The customer owns an active entitlement.
         * 3. The entitlement permits reading.
         * 4. The entitlement has not been revoked.
         * 5. The entitlement has not expired.
         *
         * `data` remains the book collection for
         * backward compatibility.
         */
        $books = Book::query()
            ->select([
                'books.id',
                'books.uuid',
                'books.title',
                'books.slug',
                'books.subtitle',
                'books.description',
                'books.author',
                'books.cover_image',
                'books.is_published',
                'books.published_at',
            ])
            ->with([
                'categories:id,uuid,name,slug',
            ])
            ->where('books.is_published', true)
            ->whereHas('entitlements', function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('can_read', true)
                    ->whereNull('revoked_at')
                    ->where(function ($entitlementQuery) {
                        $entitlementQuery
                            ->whereNull('expires_at')
                            ->orWhere(
                                'expires_at',
                                '>',
                                now()
                            );
                    });
            })
            ->orderByDesc('books.published_at')
            ->get();

        /*
         * ---------------------------------------------------------
         * OWNED AUDIOBOOKS
         * ---------------------------------------------------------
         *
         * An audiobook appears in the library only when:
         *
         * 1. The audiobook is active.
         * 2. It is not scheduled for future publication.
         * 3. The customer owns an active entitlement.
         * 4. The entitlement permits streaming.
         * 5. The entitlement has not been revoked.
         * 6. The entitlement has not expired.
         *
         * IMPORTANT:
         *
         * Private audio file paths are intentionally not selected.
         */
        $audiobooks = Audiobook::query()
            ->select([
                'audiobooks.id',
                'audiobooks.uuid',
                'audiobooks.book_id',
                'audiobooks.description',
                'audiobooks.cover_image',
                'audiobooks.price',
                'audiobooks.currency',
                'audiobooks.status',
                'audiobooks.duration_seconds',
                'audiobooks.published_at',
            ])
            ->with([
                'book:id,uuid,title,slug,subtitle,author,cover_image',
            ])
            ->where('audiobooks.status', 'active')
            ->where(function ($query) {
                $query
                    ->whereNull('audiobooks.published_at')
                    ->orWhere(
                        'audiobooks.published_at',
                        '<=',
                        now()
                    );
            })
            ->whereHas('entitlements', function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('can_stream', true)
                    ->whereNull('revoked_at')
                    ->where(function ($entitlementQuery) {
                        $entitlementQuery
                            ->whereNull('expires_at')
                            ->orWhere(
                                'expires_at',
                                '>',
                                now()
                            );
                    });
            })
            ->orderByDesc('audiobooks.published_at')
            ->get();

        /*
         * ---------------------------------------------------------
         * OWNED COURSES
         * ---------------------------------------------------------
         *
         * A course appears in the library only when:
         *
         * 1. The course is active.
         * 2. It is not scheduled for future publication.
         * 3. The customer owns an active entitlement.
         * 4. The entitlement permits course access.
         * 5. The entitlement has not been revoked.
         * 6. The entitlement has not expired.
         *
         * We intentionally return course catalogue information only.
         *
         * Lesson progress remains handled by the dedicated
         * course lesson progress endpoints.
         */
        $courses = Course::query()
            ->select([
                'courses.id',
                'courses.uuid',
                'courses.title',
                'courses.slug',
                'courses.subtitle',
                'courses.description',
                'courses.cover_image',
                'courses.price',
                'courses.currency',
                'courses.status',
                'courses.published_at',
            ])
            ->withCount([
                'lessons' => function ($query) {
                    $query
                        ->where('status', 'active')
                        ->where(function ($lessonQuery) {
                            $lessonQuery
                                ->whereNull('published_at')
                                ->orWhere(
                                    'published_at',
                                    '<=',
                                    now()
                                );
                        });
                },
            ])
            ->where('courses.status', 'active')
            ->where(function ($query) {
                $query
                    ->whereNull('courses.published_at')
                    ->orWhere(
                        'courses.published_at',
                        '<=',
                        now()
                    );
            })
            ->whereHas('entitlements', function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('can_access', true)
                    ->whereNull('revoked_at')
                    ->where(function ($entitlementQuery) {
                        $entitlementQuery
                            ->whereNull('expires_at')
                            ->orWhere(
                                'expires_at',
                                '>',
                                now()
                            );
                    });
            })
            ->orderByDesc('courses.published_at')
            ->orderByDesc('courses.id')
            ->get();

        /*
         * ---------------------------------------------------------
         * RESPONSE
         * ---------------------------------------------------------
         *
         * `data` remains the book collection.
         *
         * Audiobooks and courses are exposed through their
         * respective collections.
         */
        return response()->json([
            'data' => $books,

            'audiobooks' => $audiobooks,

            'courses' => $courses,
        ]);
    }
}