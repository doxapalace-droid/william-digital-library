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
use App\Http\Controllers\Api\BundleController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ContinueReadingController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseLessonController;
use App\Http\Controllers\Api\CourseLessonProgressController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FreeProductController;
use App\Http\Controllers\Api\HighlightController;
use App\Http\Controllers\Api\MembershipPlanController;
use App\Http\Controllers\Api\MyLibraryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PodcastController;
use App\Http\Controllers\Api\PodcastEpisodeController;
use App\Http\Controllers\Api\PodcastEpisodeProgressController;
use App\Http\Controllers\Api\PodcastStreamController;
use App\Http\Controllers\Api\ReaderPreferenceController;
use App\Http\Controllers\Api\ReadingNoteController;
use App\Http\Controllers\Api\ReadingProgressController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RecentlyViewedController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\VideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| PUBLIC API
|--------------------------------------------------------------------------
|
| These routes do not require authentication.
|
*/


/*
|--------------------------------------------------------------------------
| Books
|--------------------------------------------------------------------------
*/

Route::get('/books', [
    BookController::class,
    'index',
])->name('books.index');

/*
 * IMPORTANT:
 * Search must remain before /books/{slug}.
 */
Route::get('/books/search', [
    BookController::class,
    'search',
])->name('books.search');

Route::get('/books/{slug}', [
    BookController::class,
    'show',
])->name('books.show');


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

Route::get('/categories', [
    CategoryController::class,
    'index',
])->name('categories.index');

Route::get('/categories/{category}', [
    CategoryController::class,
    'show',
])->name('categories.show');


/*
|--------------------------------------------------------------------------
| Authors
|--------------------------------------------------------------------------
*/

Route::get('/authors', [
    AuthorController::class,
    'index',
])->name('authors.index');

Route::get('/authors/{author}', [
    AuthorController::class,
    'show',
])->name('authors.show');


/*
|--------------------------------------------------------------------------
| Audiobooks
|--------------------------------------------------------------------------
|
| These endpoints expose metadata only.
| Private audio files are never exposed here.
|
*/

Route::get('/audiobooks', [
    AudiobookController::class,
    'index',
])->name('audiobooks.index');

Route::get('/audiobooks/{audiobook}', [
    AudiobookController::class,
    'show',
])->name('audiobooks.show');

Route::get('/audiobooks/{audiobook}/chapters', [
    AudiobookChapterController::class,
    'index',
])->name('audiobooks.chapters.index');


/*
|--------------------------------------------------------------------------
| Videos
|--------------------------------------------------------------------------
*/

Route::get('/videos', [
    VideoController::class,
    'index',
])->name('videos.index');

Route::get('/videos/{video}', [
    VideoController::class,
    'show',
])->name('videos.show');


/*
|--------------------------------------------------------------------------
| Podcasts
|--------------------------------------------------------------------------
*/

Route::get('/podcasts', [
    PodcastController::class,
    'index',
])->name('podcasts.index');

Route::get('/podcasts/{podcast}', [
    PodcastController::class,
    'show',
])->name('podcasts.show');

Route::get('/podcasts/{podcast}/episodes', [
    PodcastEpisodeController::class,
    'index',
])->name('podcasts.episodes.index');

Route::get('/podcasts/{podcast}/episodes/{episode}', [
    PodcastEpisodeController::class,
    'show',
])->name('podcasts.episodes.show');


/*
|--------------------------------------------------------------------------
| Public Podcast Streaming
|--------------------------------------------------------------------------
|
| Podcast media remains in private storage.
| These controllers stream the files without exposing
| their physical storage paths.
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
| Courses
|--------------------------------------------------------------------------
*/

Route::get('/courses', [
    CourseController::class,
    'index',
])->name('courses.index');

Route::get('/courses/{course}', [
    CourseController::class,
    'show',
])->name('courses.show');


/*
|--------------------------------------------------------------------------
| Course Lessons
|--------------------------------------------------------------------------
|
| The lesson controller determines whether the lesson is:
|
| - publicly available
| - a free preview
| - protected by course entitlement
|
*/

Route::get('/courses/{course}/lessons/{lesson}', [
    CourseLessonController::class,
    'show',
])->name('courses.lessons.show');


/*
|--------------------------------------------------------------------------
| Membership Plans
|--------------------------------------------------------------------------
|
| Membership plans are public catalogue data.
|
| Customers can:
|
| - view all published plans
| - view one published plan
|
*/

Route::get('/membership-plans', [
    MembershipPlanController::class,
    'index',
])->name('membership-plans.index');

Route::get('/membership-plans/{membershipPlan}', [
    MembershipPlanController::class,
    'show',
])->name('membership-plans.show');


/*
|--------------------------------------------------------------------------
| Bundles
|--------------------------------------------------------------------------
|
| Bundles are public catalogue products.
|
| Customers can:
|
| - view all published bundles
| - view one published bundle
|
| Purchasing a bundle is handled separately through
| the authenticated cart/checkout system.
|
*/

Route::get('/bundles', [
    BundleController::class,
    'index',
])->name('bundles.index');

Route::get('/bundles/{bundle}', [
    BundleController::class,
    'show',
])->name('bundles.show');


/*
|--------------------------------------------------------------------------
| ADMIN API
|--------------------------------------------------------------------------
|
| All routes in this group require:
|
| - Sanctum authentication
| - admin role
|
*/

Route::middleware([
    'auth:sanctum',
    'role:admin',
])->prefix('admin')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Books
    |--------------------------------------------------------------------------
    */

    Route::post('/books', [
        BookController::class,
        'store',
    ])->name('admin.books.store');

    Route::put('/books/{uuid}', [
        BookController::class,
        'update',
    ])->name('admin.books.update');

    Route::delete('/books/{uuid}', [
        BookController::class,
        'destroy',
    ])->name('admin.books.destroy');


    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    Route::post('/categories', [
        CategoryController::class,
        'store',
    ])->name('admin.categories.store');

    Route::put('/categories/{category}', [
        CategoryController::class,
        'update',
    ])->name('admin.categories.update');

    Route::delete('/categories/{category}', [
        CategoryController::class,
        'destroy',
    ])->name('admin.categories.destroy');


    /*
    |--------------------------------------------------------------------------
    | Authors
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | The controller methods receive:
    |
    |     Author $author
    |
    | Therefore the route parameter must be:
    |
    |     {author}
    |
    */

    Route::post('/authors', [
        AuthorController::class,
        'store',
    ])->name('admin.authors.store');

    Route::put('/authors/{author}', [
        AuthorController::class,
        'update',
    ])->name('admin.authors.update');

    Route::patch('/authors/{author}', [
        AuthorController::class,
        'update',
    ])->name('admin.authors.update.patch');

    Route::delete('/authors/{author}', [
        AuthorController::class,
        'destroy',
    ])->name('admin.authors.destroy');


    /*
    |--------------------------------------------------------------------------
    | Audiobooks
    |--------------------------------------------------------------------------
    */

    Route::get('/audiobooks', [
        AdminAudiobookController::class,
        'index',
    ])->name('admin.audiobooks.index');

    Route::post('/audiobooks', [
        AdminAudiobookController::class,
        'store',
    ])->name('admin.audiobooks.store');

    Route::get('/audiobooks/{audiobook}', [
        AdminAudiobookController::class,
        'show',
    ])->name('admin.audiobooks.show');

    Route::put('/audiobooks/{audiobook}', [
        AdminAudiobookController::class,
        'update',
    ])->name('admin.audiobooks.update');

    Route::patch('/audiobooks/{audiobook}', [
        AdminAudiobookController::class,
        'update',
    ])->name('admin.audiobooks.update.patch');

    Route::delete('/audiobooks/{audiobook}', [
        AdminAudiobookController::class,
        'destroy',
    ])->name('admin.audiobooks.destroy');


    /*
    |--------------------------------------------------------------------------
    | Audiobook Chapters
    |--------------------------------------------------------------------------
    */

    Route::get('/audiobooks/{audiobook}/chapters', [
        AdminAudiobookChapterController::class,
        'index',
    ])->name('admin.audiobooks.chapters.index');

    Route::post('/audiobooks/{audiobook}/chapters', [
        AdminAudiobookChapterController::class,
        'store',
    ])->name('admin.audiobooks.chapters.store');

    Route::get('/audiobooks/{audiobook}/chapters/{chapter}', [
        AdminAudiobookChapterController::class,
        'show',
    ])->name('admin.audiobooks.chapters.show');

    Route::put('/audiobooks/{audiobook}/chapters/{chapter}', [
        AdminAudiobookChapterController::class,
        'update',
    ])->name('admin.audiobooks.chapters.update');

    Route::patch('/audiobooks/{audiobook}/chapters/{chapter}', [
        AdminAudiobookChapterController::class,
        'update',
    ])->name('admin.audiobooks.chapters.update.patch');

    Route::delete('/audiobooks/{audiobook}/chapters/{chapter}', [
        AdminAudiobookChapterController::class,
        'destroy',
    ])->name('admin.audiobooks.chapters.destroy');


    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    Route::get('/orders', [
        AdminOrderController::class,
        'index',
    ])->name('admin.orders.index');

    Route::get('/orders/{uuid}', [
        AdminOrderController::class,
        'show',
    ])->name('admin.orders.show');

    Route::put('/orders/{uuid}', [
        AdminOrderController::class,
        'update',
    ])->name('admin.orders.update');
});


/*
|--------------------------------------------------------------------------
| AUTHENTICATED CUSTOMER API
|--------------------------------------------------------------------------
|
| All routes below require a logged-in customer.
|
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Current User
    |--------------------------------------------------------------------------
    */

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->name('user.current');


    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    Route::get('/orders', [
        OrderController::class,
        'index',
    ])->name('orders.index');

    Route::get('/orders/{uuid}', [
        OrderController::class,
        'show',
    ])->name('orders.show');


    /*
    |--------------------------------------------------------------------------
    | Membership Subscriptions
    |--------------------------------------------------------------------------
    |
    | Customers can:
    |
    | - view their current subscription
    | - create a subscription
    | - cancel their subscription
    |
    */

    Route::get('/subscription', [
        SubscriptionController::class,
        'current',
    ])->name('subscription.current');

    Route::post('/subscriptions', [
        SubscriptionController::class,
        'store',
    ])->name('subscriptions.store');

    Route::post('/subscriptions/{subscription}/cancel', [
        SubscriptionController::class,
        'cancel',
    ])->name('subscriptions.cancel');


    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/reviews', [
        ReviewController::class,
        'index',
    ])->name('books.reviews.index');

    Route::get('/books/{uuid}/reviews/statistics', [
        ReviewController::class,
        'statistics',
    ])->name('books.reviews.statistics');

    Route::post('/books/{uuid}/reviews', [
        ReviewController::class,
        'store',
    ])->name('books.reviews.store');

    Route::put('/books/{uuid}/reviews/{review}', [
        ReviewController::class,
        'update',
    ])->name('books.reviews.update');

    Route::patch('/books/{uuid}/reviews/{review}', [
        ReviewController::class,
        'update',
    ])->name('books.reviews.update.patch');

    Route::delete('/books/{uuid}/reviews/{review}', [
        ReviewController::class,
        'destroy',
    ])->name('books.reviews.destroy');


    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    Route::get('/orders/{uuid}/payments', [
        PaymentController::class,
        'index',
    ])->name('orders.payments.index');

    Route::post('/payments', [
        PaymentController::class,
        'store',
    ])->name('payments.store');

    Route::get('/payments/{uuid}', [
        PaymentController::class,
        'show',
    ])->name('payments.show');

    Route::post('/payments/{uuid}/verify', [
        PaymentController::class,
        'verify',
    ])->name('payments.verify');


    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */

    Route::get('/cart', [
        CartController::class,
        'index',
    ])->name('cart.index');

    Route::post('/cart', [
        CartController::class,
        'store',
    ])->name('cart.store');

    Route::delete('/cart/{uuid}', [
        CartController::class,
        'destroy',
    ])->name('cart.destroy');


    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    */

    Route::get('/checkout', [
        CheckoutController::class,
        'index',
    ])->name('checkout.index');

    Route::post('/checkout', [
        CheckoutController::class,
        'store',
    ])->name('checkout.store');


    /*
    |--------------------------------------------------------------------------
    | Free Products
    |--------------------------------------------------------------------------
    |
    | Free products do not go through checkout or payment.
    |
    | A successful claim creates the appropriate entitlement:
    |
    | - Book        -> BookEntitlement
    | - Audiobook   -> AudiobookEntitlement
    | - Course      -> CourseEntitlement
    |
    */

    Route::post('/free-products/books/{book}/claim', [
        FreeProductController::class,
        'claimBook',
    ])->name('free-products.books.claim');

    Route::post('/free-products/audiobooks/{audiobook}/claim', [
        FreeProductController::class,
        'claimAudiobook',
    ])->name('free-products.audiobooks.claim');

    Route::post('/free-products/courses/{course}/claim', [
        FreeProductController::class,
        'claimCourse',
    ])->name('free-products.courses.claim');


    /*
    |--------------------------------------------------------------------------
    | My Library
    |--------------------------------------------------------------------------
    |
    | Returns the authenticated user's:
    |
    | - books
    | - audiobooks
    | - courses
    |
    */

    Route::get('/my-library', [
        MyLibraryController::class,
        'index',
    ])->name('my-library.index');


    /*
    |--------------------------------------------------------------------------
    | Continue Reading
    |--------------------------------------------------------------------------
    */

    Route::get('/continue-reading', [
        ContinueReadingController::class,
        'index',
    ])->name('continue-reading.index');


    /*
    |--------------------------------------------------------------------------
    | Recently Viewed
    |--------------------------------------------------------------------------
    */

    Route::get('/recently-viewed', [
        RecentlyViewedController::class,
        'index',
    ])->name('recently-viewed.index');

    Route::post('/books/{uuid}/recently-viewed', [
        RecentlyViewedController::class,
        'store',
    ])->name('books.recently-viewed.store');


    /*
    |--------------------------------------------------------------------------
    | Recommendations
    |--------------------------------------------------------------------------
    */

    Route::get('/recommendations', [
        RecommendationController::class,
        'index',
    ])->name('recommendations.index');


    /*
    |--------------------------------------------------------------------------
    | Reader Preferences
    |--------------------------------------------------------------------------
    */

    Route::get('/reader-preferences', [
        ReaderPreferenceController::class,
        'show',
    ])->name('reader-preferences.show');

    Route::post('/reader-preferences', [
        ReaderPreferenceController::class,
        'store',
    ])->name('reader-preferences.store');

    Route::put('/reader-preferences', [
        ReaderPreferenceController::class,
        'update',
    ])->name('reader-preferences.update');


    /*
    |--------------------------------------------------------------------------
    | Book Reader
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{book}/read', [
        BookReaderController::class,
        'show',
    ])->name('books.read');


    /*
    |--------------------------------------------------------------------------
    | Book Download
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{book}/download', [
        BookDownloadController::class,
        'download',
    ])->name('books.download');


    /*
    |--------------------------------------------------------------------------
    | Audiobook Streaming
    |--------------------------------------------------------------------------
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
    ])->name('audiobooks.download');


    /*
    |--------------------------------------------------------------------------
    | Audiobook Listening Progress
    |--------------------------------------------------------------------------
    */

    Route::get('/audiobooks/{audiobook}/progress', [
        AudiobookListeningProgressController::class,
        'show',
    ])->name('audiobooks.progress.show');

    Route::put('/audiobooks/{audiobook}/progress', [
        AudiobookListeningProgressController::class,
        'update',
    ])->name('audiobooks.progress.update');


    /*
    |--------------------------------------------------------------------------
    | Podcast Continue Listening
    |--------------------------------------------------------------------------
    */

    Route::get('/podcast-progress/continue-listening', [
        PodcastEpisodeProgressController::class,
        'continueListening',
    ])->name('podcast-progress.continue-listening');


    /*
    |--------------------------------------------------------------------------
    | Podcast Episode Progress
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/podcasts/{podcast}/episodes/{episode}/progress',
        [
            PodcastEpisodeProgressController::class,
            'show',
        ]
    )->name('podcasts.episodes.progress.show');

    Route::put(
        '/podcasts/{podcast}/episodes/{episode}/progress',
        [
            PodcastEpisodeProgressController::class,
            'update',
        ]
    )->name('podcasts.episodes.progress.update');

    Route::post(
        '/podcasts/{podcast}/episodes/{episode}/progress/complete',
        [
            PodcastEpisodeProgressController::class,
            'complete',
        ]
    )->name('podcasts.episodes.progress.complete');


    /*
    |--------------------------------------------------------------------------
    | Book Reading Progress
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/progress', [
        ReadingProgressController::class,
        'show',
    ])->name('books.progress.show');

    Route::put('/books/{uuid}/progress', [
        ReadingProgressController::class,
        'update',
    ])->name('books.progress.update');


    /*
    |--------------------------------------------------------------------------
    | Course Progress
    |--------------------------------------------------------------------------
    */

    Route::get('/courses/{course}/progress', [
        CourseLessonProgressController::class,
        'course',
    ])->name('courses.progress');

    Route::get(
        '/courses/{course}/lessons/{lesson}/progress',
        [
            CourseLessonProgressController::class,
            'show',
        ]
    )->name('courses.lessons.progress.show');

    Route::put(
        '/courses/{course}/lessons/{lesson}/progress',
        [
            CourseLessonProgressController::class,
            'update',
        ]
    )->name('courses.lessons.progress.update');

    Route::post(
        '/courses/{course}/lessons/{lesson}/complete',
        [
            CourseLessonProgressController::class,
            'complete',
        ]
    )->name('courses.lessons.complete');


    /*
    |--------------------------------------------------------------------------
    | Favorites
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/favorites', [
        FavoriteController::class,
        'show',
    ])->name('books.favorites.show');

    Route::post('/books/{uuid}/favorites', [
        FavoriteController::class,
        'store',
    ])->name('books.favorites.store');

    Route::delete('/books/{uuid}/favorites', [
        FavoriteController::class,
        'destroy',
    ])->name('books.favorites.destroy');


    /*
    |--------------------------------------------------------------------------
    | Bookmarks
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/bookmarks', [
        BookmarkController::class,
        'index',
    ])->name('books.bookmarks.index');

    Route::post('/books/{uuid}/bookmarks', [
        BookmarkController::class,
        'store',
    ])->name('books.bookmarks.store');

    Route::delete(
        '/books/{uuid}/bookmarks/{bookmark}',
        [
            BookmarkController::class,
            'destroy',
        ]
    )->name('books.bookmarks.destroy');


    /*
    |--------------------------------------------------------------------------
    | Highlights
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/highlights', [
        HighlightController::class,
        'index',
    ])->name('books.highlights.index');

    Route::post('/books/{uuid}/highlights', [
        HighlightController::class,
        'store',
    ])->name('books.highlights.store');

    Route::put('/books/{uuid}/highlights/{highlight}', [
        HighlightController::class,
        'update',
    ])->name('books.highlights.update');

    Route::patch('/books/{uuid}/highlights/{highlight}', [
        HighlightController::class,
        'update',
    ])->name('books.highlights.update.patch');

    Route::delete('/books/{uuid}/highlights/{highlight}', [
        HighlightController::class,
        'destroy',
    ])->name('books.highlights.destroy');


    /*
    |--------------------------------------------------------------------------
    | Reading Notes
    |--------------------------------------------------------------------------
    */

    Route::get('/books/{uuid}/notes', [
        ReadingNoteController::class,
        'index',
    ])->name('books.notes.index');

    Route::post('/books/{uuid}/notes', [
        ReadingNoteController::class,
        'store',
    ])->name('books.notes.store');

    Route::put('/books/{uuid}/notes/{readingNote}', [
        ReadingNoteController::class,
        'update',
    ])->name('books.notes.update');

    Route::patch('/books/{uuid}/notes/{readingNote}', [
        ReadingNoteController::class,
        'update',
    ])->name('books.notes.update.patch');

    Route::delete('/books/{uuid}/notes/{readingNote}', [
        ReadingNoteController::class,
        'destroy',
    ])->name('books.notes.destroy');
});