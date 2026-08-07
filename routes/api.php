<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookDownloadController;
use App\Http\Controllers\Api\BookReaderController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CategoryController;
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
| Only published books and active categories should be exposed by
| their respective controllers.
|
*/


/*
|--------------------------------------------------------------------------
| Public Book Catalogue
|--------------------------------------------------------------------------
|
| Returns the public catalogue of published books.
|
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
| Allows visitors to search and filter the public book catalogue.
|
| Supported functionality:
|
| - Search published books by title.
| - Search published books by author.
| - Case-insensitive searching.
| - Filter published books by category.
| - Combine a text search with a category filter.
| - Return an empty collection when nothing matches.
|
| Only published books should be returned by the controller.
|
| Examples:
|
| GET /api/books/search?q=Binding
| GET /api/books/search?q=William
| GET /api/books/search?category=1
| GET /api/books/search?q=Dominion&category=1
|
| IMPORTANT:
| This route must remain BEFORE /books/{slug}.
| Otherwise Laravel may interpret "search" as a book slug.
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
|
| Returns a single published book using its public slug.
|
*/

Route::get('/books/{slug}', [
    BookController::class,
    'show',
]);


/*
|--------------------------------------------------------------------------
| Public Categories
|--------------------------------------------------------------------------
|
| Returns active public categories and their public details.
|
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
    |
    | Administrators can create, update, and delete books.
    |
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
    |
    | Administrators can create, update, and delete categories.
    |
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
| Every route in this group requires a valid Sanctum-authenticated user.
| Individual controllers remain responsible for verifying ownership,
| entitlement, and access to book-specific resources.
|
*/

Route::middleware('auth:sanctum')->group(function () {


    /*
    |--------------------------------------------------------------------------
    | Authenticated User Profile
    |--------------------------------------------------------------------------
    |
    | Returns the currently authenticated customer.
    |
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
    | Authenticated users can retrieve and update their personal reader
    | appearance and layout preferences.
    |
    | Each preference record belongs exclusively to the authenticated user.
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
    | Allows an authenticated customer to securely read a book.
    |
    | BookReaderController must verify that the authenticated customer
    | currently has a valid entitlement to the requested book.
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
    | Allows an authenticated customer to download an entitled book.
    |
    | BookDownloadController must verify book access before returning
    | or streaming the protected file.
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
    | Customers may retrieve and update their reading position.
    |
    | ReadingProgressController additionally verifies that the customer
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
    | Favorites
    |--------------------------------------------------------------------------
    |
    | Favorites allow customers to mark entitled books for quick access.
    |
    | GET:
    | Determines whether the requested book is currently favorited by
    | the authenticated customer.
    |
    | POST:
    | Adds the requested book to the authenticated customer's favorites.
    | The controller should prevent duplicate favorite records.
    |
    | DELETE:
    | Removes the requested book from the authenticated customer's
    | favorites.
    |
    | FavoriteController must verify that the authenticated customer
    | currently has access to the requested book.
    |
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
    |
    | Authenticated customers can create, view, and remove bookmarks
    | for books they are currently entitled to read.
    |
    | BookmarkController must verify both entitlement and ownership
    | before modifying a bookmark.
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
    | HighlightController must ensure that users cannot access or modify
    | another customer's highlights.
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
    | ReadingNoteController must ensure that notes remain private to
    | the authenticated customer.
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