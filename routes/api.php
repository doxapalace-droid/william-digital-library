<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RecentlyViewedController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookDownloadController;
use App\Http\Controllers\Api\BookReaderController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\ContinueReadingController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HighlightController;
use App\Http\Controllers\Api\MyLibraryController;
use App\Http\Controllers\Api\ReaderPreferenceController;
use App\Http\Controllers\Api\ReadingNoteController;
use App\Http\Controllers\Api\ReadingProgressController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
|
| These routes are available without authentication.
| Only published books, active categories, and active authors should be
| exposed by their respective controllers.
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
| IMPORTANT:
| Keep this route BEFORE /books/{slug}.
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
| Admin Routes
|--------------------------------------------------------------------------
|
| These routes require authentication and the admin role.
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
});


/*
|--------------------------------------------------------------------------
| Authenticated User / Customer Routes
|--------------------------------------------------------------------------
|
| Every route in this group requires a valid Sanctum-authenticated user.
|
*/

Route::middleware('auth:sanctum')->group(function () {

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
    | Customer Cart
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
    | Customer Checkout
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
    | Authenticated User Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/user', function (Request $request) {
        return $request->user();
    });


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
    | Reading Progress
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