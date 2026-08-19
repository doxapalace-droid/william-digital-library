<?php

use App\Http\Controllers\Api\AdminAudiobookChapterController;
use App\Http\Controllers\Api\AdminAudiobookController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\AudiobookChapterController;
use App\Http\Controllers\Api\AudiobookController;
use App\Http\Controllers\Api\AudiobookDownloadController;
use App\Http\Controllers\Api\AudiobookListeningProgressController;
use App\Http\Controllers\Api\AudiobookStreamController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookDownloadController;
use App\Http\Controllers\Api\BookReaderController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ContinueReadingController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseLessonController;
use App\Http\Controllers\Api\CourseLessonProgressController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HighlightController;
use App\Http\Controllers\Api\MyLibraryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PodcastController;
use App\Http\Controllers\Api\PodcastEpisodeController;
use App\Http\Controllers\Api\PodcastStreamController;
use App\Http\Controllers\Api\ReaderPreferenceController;
use App\Http\Controllers\Api\ReadingNoteController;
use App\Http\Controllers\Api\ReadingProgressController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RecentlyViewedController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\VideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
|
| These routes are available without authentication.
|
*/


/*
|--------------------------------------------------------------------------
| Public Book Catalogue
|--------------------------------------------------------------------------
*/

Route::get('/books', [
    BookController::class,
    'index',
]);


/*
|--------------------------------------------------------------------------
| Book Search
|--------------------------------------------------------------------------
|
| Keep this route before /books/{slug}.
|
*/

Route::get('/books/search', [
    BookController::class,
    'search',
]);


/*
|--------------------------------------------------------------------------
| Public Book Details
|--------------------------------------------------------------------------
*/

Route::get('/books/{slug}', [
    BookController::class,
    'show',
]);


/*
|--------------------------------------------------------------------------
| Public Categories
|--------------------------------------------------------------------------
*/

Route::get('/categories', [
    CategoryController::class,
    'index',
]);

Route::get('/categories/{category}', [
    CategoryController::class,
    'show',
]);


/*
|--------------------------------------------------------------------------
| Public Authors
|--------------------------------------------------------------------------
*/

Route::get('/authors', [
    AuthorController::class,
    'index',
]);

Route::get('/authors/{author}', [
    AuthorController::class,
    'show',
]);


/*
|--------------------------------------------------------------------------
| Public Audiobooks
|--------------------------------------------------------------------------
|
| Public audiobook metadata only.
| Private audio files are never exposed here.
|
*/

Route::get('/audiobooks', [
    AudiobookController::class,
    'index',
]);

Route::get('/audiobooks/{audiobook}', [
    AudiobookController::class,
    'show',
]);

Route::get('/audiobooks/{audiobook}/chapters', [
    AudiobookChapterController::class,
    'index',
]);


/*
|--------------------------------------------------------------------------
| Public Videos
|--------------------------------------------------------------------------
|
| Public video metadata only.
| Private video files are never exposed here.
|
*/

Route::get('/videos', [
    VideoController::class,
    'index',
]);

Route::get('/videos/{video}', [
    VideoController::class,
    'show',
]);


/*
|--------------------------------------------------------------------------
| Public Podcasts
|--------------------------------------------------------------------------
|
| Podcasts are the library's free-content area.
|
| Visitors can browse podcasts and their publicly
| available episodes without authentication.
|
| Private audio/video file paths are never exposed
| through catalogue endpoints.
|
*/

Route::get('/podcasts', [
    PodcastController::class,
    'index',
]);

Route::get('/podcasts/{podcast}', [
    PodcastController::class,
    'show',
]);

Route::get('/podcasts/{podcast}/episodes', [
    PodcastEpisodeController::class,
    'index',
]);

Route::get('/podcasts/{podcast}/episodes/{episode}', [
    PodcastEpisodeController::class,
    'show',
]);


/*
|--------------------------------------------------------------------------
| Public Podcast Streaming
|--------------------------------------------------------------------------
|
| Podcast content is free.
|
| Authentication is therefore NOT required.
|
| The actual audio/video files remain in private storage.
| These controlled endpoints stream the files without
| exposing their physical storage paths.
|
| HTTP Range requests are supported for:
|
| - seeking
| - pausing
| - resuming
| - browser media players
| - podcast applications
|
*/

Route::get(
    '/podcasts/{podcast}/episodes/{episode}/audio',
    [
        PodcastStreamController::class,
        'audio',
    ]
)->name('podcasts.episodes.audio');

Route::get(
    '/podcasts/{podcast}/episodes/{episode}/video',
    [
        PodcastStreamController::class,
        'video',
    ]
)->name('podcasts.episodes.video');


/*
|--------------------------------------------------------------------------
| Public Courses
|--------------------------------------------------------------------------
|
| Only active and currently published courses are returned.
|
*/

Route::get('/courses', [
    CourseController::class,
    'index',
]);

Route::get('/courses/{course}', [
    CourseController::class,
    'show',
]);


/*
|--------------------------------------------------------------------------
| Public Course Lesson Access
|--------------------------------------------------------------------------
|
| Preview lessons may be accessed by guests.
|
| Paid lessons are protected by CourseLessonController,
| which checks authentication and course entitlement.
|
| This route MUST remain outside auth:sanctum.
|
*/

Route::get('/courses/{course}/lessons/{lesson}', [
    CourseLessonController::class,
    'show',
]);


/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Authentication + admin role required.
|
*/

Route::middleware([
    'auth:sanctum',
    'role:admin',
])->prefix('admin')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Book Management
    |--------------------------------------------------------------------------
    */

    Route::post('/books', [
        BookController::class,
        'store',
    ]);

    Route::put('/books/{uuid}', [
        BookController::class,
        'update',
    ]);

    Route::delete('/books/{uuid}', [
        BookController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Category Management
    |--------------------------------------------------------------------------
    */

    Route::post('/categories', [
        CategoryController::class,
        'store',
    ]);

    Route::put('/categories/{category}', [
        CategoryController::class,
        'update',
    ]);

    Route::delete('/categories/{category}', [
        CategoryController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Author Management
    |--------------------------------------------------------------------------
    */

    Route::post('/authors', [
        AuthorController::class,
        'store',
    ]);

    Route::put('/authors/{author}', [
        AuthorController::class,
        'update',
    ]);

    Route::delete('/authors/{author}', [
        AuthorController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Audiobook Management
    |--------------------------------------------------------------------------
    */

    Route::get('/audiobooks', [
        AdminAudiobookController::class,
        'index',
    ]);

    Route::post('/audiobooks', [
        AdminAudiobookController::class,
        'store',
    ]);

    Route::get('/audiobooks/{audiobook}', [
        AdminAudiobookController::class,
        'show',
    ]);

    Route::put('/audiobooks/{audiobook}', [
        AdminAudiobookController::class,
        'update',
    ]);

    Route::patch('/audiobooks/{audiobook}', [
        AdminAudiobookController::class,
        'update',
    ]);

    Route::delete('/audiobooks/{audiobook}', [
        AdminAudiobookController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Audiobook Chapter Management
    |--------------------------------------------------------------------------
    */

    Route::get('/audiobooks/{audiobook}/chapters', [
        AdminAudiobookChapterController::class,
        'index',
    ]);

    Route::post('/audiobooks/{audiobook}/chapters', [
        AdminAudiobookChapterController::class,
        'store',
    ]);

    Route::get('/audiobooks/{audiobook}/chapters/{chapter}', [
        AdminAudiobookChapterController::class,
        'show',
    ]);

    Route::put('/audiobooks/{audiobook}/chapters/{chapter}', [
        AdminAudiobookChapterController::class,
        'update',
    ]);

    Route::patch('/audiobooks/{audiobook}/chapters/{chapter}', [
        AdminAudiobookChapterController::class,
        'update',
    ]);

    Route::delete('/audiobooks/{audiobook}/chapters/{chapter}', [
        AdminAudiobookChapterController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Order Management
    |--------------------------------------------------------------------------
    */

    Route::get('/orders', [
        AdminOrderController::class,
        'index',
    ]);

    Route::get('/orders/{uuid}', [
        AdminOrderController::class,
        'show',
    ]);

    Route::put('/orders/{uuid}', [
        AdminOrderController::class,
        'update',
    ]);
});


/*
|--------------------------------------------------------------------------
| Authenticated Customer Routes
|--------------------------------------------------------------------------
|
| Every route in this group requires Sanctum authentication.
|
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authenticated User
    |--------------------------------------------------------------------------
    */

    Route::get('/user', function (Request $request) {
        return $request->user();
    });


    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    Route::get('/orders', [
        OrderController::class,
        'index',
    ]);

    Route::get('/orders/{uuid}', [
        OrderController::class,
        'show',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/reviews', [
        ReviewController::class,
        'index',
    ]);

    Route::get('/books/{uuid}/reviews/statistics', [
        ReviewController::class,
        'statistics',
    ]);

    Route::post('/books/{uuid}/reviews', [
        ReviewController::class,
        'store',
    ]);

    Route::put('/books/{uuid}/reviews/{review}', [
        ReviewController::class,
        'update',
    ]);

    Route::patch('/books/{uuid}/reviews/{review}', [
        ReviewController::class,
        'update',
    ]);

    Route::delete('/books/{uuid}/reviews/{review}', [
        ReviewController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    Route::get('/orders/{uuid}/payments', [
        PaymentController::class,
        'index',
    ]);

    Route::post('/payments', [
        PaymentController::class,
        'store',
    ]);

    Route::get('/payments/{uuid}', [
        PaymentController::class,
        'show',
    ]);

    Route::post('/payments/{uuid}/verify', [
        PaymentController::class,
        'verify',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */

    Route::get('/cart', [
        CartController::class,
        'index',
    ]);

    Route::post('/cart', [
        CartController::class,
        'store',
    ]);

    Route::delete('/cart/{uuid}', [
        CartController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    */

    Route::get('/checkout', [
        CheckoutController::class,
        'index',
    ]);

    Route::post('/checkout', [
        CheckoutController::class,
        'store',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Recently Viewed
    |--------------------------------------------------------------------------
    */

    Route::get('/recently-viewed', [
        RecentlyViewedController::class,
        'index',
    ]);

    Route::post('/books/{uuid}/recently-viewed', [
        RecentlyViewedController::class,
        'store',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Recommendations
    |--------------------------------------------------------------------------
    */

    Route::get('/recommendations', [
        RecommendationController::class,
        'index',
    ]);


    /*
    |--------------------------------------------------------------------------
    | My Library
    |--------------------------------------------------------------------------
    */

    Route::get('/my-library', [
        MyLibraryController::class,
        'index',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Continue Reading
    |--------------------------------------------------------------------------
    */

    Route::get('/continue-reading', [
        ContinueReadingController::class,
        'index',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Reader Preferences
    |--------------------------------------------------------------------------
    */

    Route::get('/reader-preferences', [
        ReaderPreferenceController::class,
        'show',
    ]);

    Route::post('/reader-preferences', [
        ReaderPreferenceController::class,
        'store',
    ]);

    Route::put('/reader-preferences', [
        ReaderPreferenceController::class,
        'update',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Secure Book Reading
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{book}/read', [
        BookReaderController::class,
        'show',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Secure Book Download
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{book}/download', [
        BookDownloadController::class,
        'download',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Audiobook Streaming
    |--------------------------------------------------------------------------
    |
    | Private audiobook audio is streamed only to
    | customers with valid entitlements.
    |
    */

    Route::get('/audiobook-chapters/{chapter}/stream', [
        AudiobookStreamController::class,
        'stream',
    ])->name('audiobook-chapters.stream');


    /*
    |--------------------------------------------------------------------------
    | Audiobook Download
    |--------------------------------------------------------------------------
    */

    Route::get('/audiobooks/{audiobook}/download', [
        AudiobookDownloadController::class,
        'download',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Audiobook Listening Progress
    |--------------------------------------------------------------------------
    */

    Route::get('/audiobooks/{audiobook}/progress', [
        AudiobookListeningProgressController::class,
        'show',
    ]);

    Route::put('/audiobooks/{audiobook}/progress', [
        AudiobookListeningProgressController::class,
        'update',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Book Reading Progress
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/progress', [
        ReadingProgressController::class,
        'show',
    ]);

    Route::put('/books/{uuid}/progress', [
        ReadingProgressController::class,
        'update',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Course Learning
    |--------------------------------------------------------------------------
    |
    | Course catalogue and lesson metadata are public.
    |
    | Course progress belongs to the authenticated user
    | and therefore requires Sanctum authentication.
    |
    */


    /*
    |--------------------------------------------------------------------------
    | Course Progress Summary
    |--------------------------------------------------------------------------
    */

    Route::get('/courses/{course}/progress', [
        CourseLessonProgressController::class,
        'course',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Individual Lesson Progress
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/courses/{course}/lessons/{lesson}/progress',
        [
            CourseLessonProgressController::class,
            'show',
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Save Lesson Progress
    |--------------------------------------------------------------------------
    |
    | Saves:
    |
    | - playback position
    | - completion state
    | - last accessed time
    |
    */

    Route::put(
        '/courses/{course}/lessons/{lesson}/progress',
        [
            CourseLessonProgressController::class,
            'update',
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Complete Lesson
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/courses/{course}/lessons/{lesson}/complete',
        [
            CourseLessonProgressController::class,
            'complete',
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Favorites
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/favorites', [
        FavoriteController::class,
        'show',
    ]);

    Route::post('/books/{uuid}/favorites', [
        FavoriteController::class,
        'store',
    ]);

    Route::delete('/books/{uuid}/favorites', [
        FavoriteController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Bookmarks
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/bookmarks', [
        BookmarkController::class,
        'index',
    ]);

    Route::post('/books/{uuid}/bookmarks', [
        BookmarkController::class,
        'store',
    ]);

    Route::delete('/books/{uuid}/bookmarks/{bookmark}', [
        BookmarkController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Highlights
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/highlights', [
        HighlightController::class,
        'index',
    ]);

    Route::post('/books/{uuid}/highlights', [
        HighlightController::class,
        'store',
    ]);

    Route::put('/books/{uuid}/highlights/{highlight}', [
        HighlightController::class,
        'update',
    ]);

    Route::delete('/books/{uuid}/highlights/{highlight}', [
        HighlightController::class,
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Reading Notes
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/notes', [
        ReadingNoteController::class,
        'index',
    ]);

    Route::post('/books/{uuid}/notes', [
        ReadingNoteController::class,
        'store',
    ]);

    Route::put('/books/{uuid}/notes/{readingNote}', [
        ReadingNoteController::class,
        'update',
    ]);

    Route::delete('/books/{uuid}/notes/{readingNote}', [
        ReadingNoteController::class,
        'destroy',
    ]);
});