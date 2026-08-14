<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookReaderController extends Controller
{
    /**
     * Open a book for an authorised customer.
     */
    public function show(
        Request $request,
        Book $book
    ): StreamedResponse|JsonResponse {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Authentication safeguard
        |--------------------------------------------------------------------------
        |
        | The route should already be protected by auth:sanctum.
        | This is an additional defensive check.
        |
        */

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify reading entitlement
        |--------------------------------------------------------------------------
        */

        if (! $user->canReadBook($book)) {
            return response()->json([
                'message' => 'You do not have access to this book.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify PDF path
        |--------------------------------------------------------------------------
        */

        if (empty($book->pdf_path)) {
            return response()->json([
                'message' => 'Book file not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify physical PDF exists
        |--------------------------------------------------------------------------
        */

        $disk = Storage::disk('books');

        if (! $disk->exists($book->pdf_path)) {
            return response()->json([
                'message' => 'Book file not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Generate safe reader filename
        |--------------------------------------------------------------------------
        */

        $readerName = Str::slug($book->title) . '.pdf';

        /*
        |--------------------------------------------------------------------------
        | Stream private PDF inline
        |--------------------------------------------------------------------------
        |
        | This allows the reader application to display the PDF while the
        | underlying private storage path remains inaccessible to the client.
        |
        */

        return $disk->response(
            $book->pdf_path,
            $readerName,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $readerName . '"',
            ]
        );
    }
}