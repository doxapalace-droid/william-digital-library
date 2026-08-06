<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookDownloadController;
use App\Http\Controllers\Api\BookReaderController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MyLibraryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReadingProgressController;
use App\Http\Controllers\Api\BookmarkController;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
|
| These routes are available without authentication.
| Only published books and active categories should be exposed by
| their respective controllers.
|use App\Http\Controllers\Api\BookmarkController;
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
    |   Authenticated customers can create, view, and remove bookmarks
    |   for books they are currently entitled to read.
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





});