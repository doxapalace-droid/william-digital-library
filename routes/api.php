<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookDownloadController;
use App\Http\Controllers\Api\BookReaderController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContinueReadingController;
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
| Only published books and active categories should be exposed by
| their respective controllers.
|
*/

Route::get('/books', [
    BookController::class,
    'index',
]);

Route::get('/books/{slug}', [
    BookController::class,
    'show',
]);

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
| Admin Routes
|--------------------------------------------------------------------------
|
| These routes require authentication and the admin role.
| Administrators can manage books and categories.
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
});

/*
|--------------------------------------------------------------------------
| Authenticated User / Customer Routes
|--------------------------------------------------------------------------
|
| These routes require a valid authenticated user.
|
*/

Route::middleware('auth:sanctum')->group(function () {

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
    | My Library
    |--------------------------------------------------------------------------
    |
    | Returns only books the authenticated user is currently entitled
    | to access.
    |
    */

    Route::get('/my-library', [
        MyLibraryController::class,
        'index',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Continue Reading
    |--------------------------------------------------------------------------
    |
    | Returns books the authenticated user has started reading.
    | Books should be ordered by the user's most recent reading activity.
    |
    */

    Route::get('/continue-reading', [
        ContinueReadingController::class,
        'index',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Reader Preferences
    |--------------------------------------------------------------------------
    |
    | Authenticated users can retrieve and update their personal reading
    | preferences. These settings belong only to the authenticated user.
    |
    */

    Route::get('/reader-preferences', [
        ReaderPreferenceController::class,
        'show',
    ]);

    Route::put('/reader-preferences', [
        ReaderPreferenceController::class,
        'update',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Secure Book Reading
    |--------------------------------------------------------------------------
    |
    | Access must be verified by the BookReaderController.
    |
    */

    Route::get('/books/{book}/read', [
        BookReaderController::class,
        'show',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Secure Book Download
    |--------------------------------------------------------------------------
    |
    | Access must be verified by the BookDownloadController.
    |
    */

    Route::get('/books/{book}/download', [
        BookDownloadController::class,
        'download',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Reading Progress
    |--------------------------------------------------------------------------
    |
    | Customers may retrieve and update their reading position only while
    | authenticated. The controller additionally verifies that the customer
    | has a valid entitlement to the requested book.
    |
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
    | Bookmarks
    |--------------------------------------------------------------------------
    |
    | Authenticated customers can create, view, and remove bookmarks
    | for books they are currently entitled to read.
    |
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
    |
    | Authenticated customers can create, view, update, and remove
    | highlights for books they are currently entitled to read.
    |
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
    |
    | Authenticated customers can create, view, update, and remove
    | personal reading notes for books they are entitled to read.
    |
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