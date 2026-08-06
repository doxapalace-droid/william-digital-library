<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookDownloadController;
use App\Http\Controllers\Api\BookReaderController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{slug}', [BookController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

    Route::post('/admin/books', [BookController::class, 'store']);

    Route::put('/admin/books/{uuid}', [
        BookController::class,
        'update'
    ]);

    Route::delete('/admin/books/{uuid}', [
        BookController::class,
        'destroy'
    ]);

    Route::post('/admin/categories', [
        CategoryController::class,
        'store'
    ]);

    Route::put('/admin/categories/{category}', [
        CategoryController::class,
        'update'
    ]);

    Route::delete('/admin/categories/{category}', [
        CategoryController::class,
        'destroy'
    ]);
});

/*
|--------------------------------------------------------------------------
| Authenticated User / Customer Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    | Authenticated user profile
    */
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    /*
    | Read purchased/entitled book
    */
    Route::get('/books/{book}/read', [
        BookReaderController::class,
        'show'
    ]);

    /*
    | Download purchased/entitled book
    */
    Route::get('/books/{book}/download', [
        BookDownloadController::class,
        'download'
    ]);
});