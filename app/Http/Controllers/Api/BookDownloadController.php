<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookDownloadController extends Controller
{
    /**
     * Download a book the authenticated user is entitled to download.
     */
    public function download(
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
        | Retrieve active, non-expired entitlement
        |--------------------------------------------------------------------------
        */

        $entitlement = $user->bookEntitlements()
            ->where('book_id', $book->id)
            ->where('status', 'active')
            ->where('can_read', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Verify download permission
        |--------------------------------------------------------------------------
        */

        if (! $entitlement || ! $entitlement->can_download) {
            return response()->json([
                'message' => 'Download is not permitted.',
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
        | Generate safe download filename
        |--------------------------------------------------------------------------
        */

        $downloadName = Str::slug($book->title) . '.pdf';

        /*
        |--------------------------------------------------------------------------
        | Download private PDF
        |--------------------------------------------------------------------------
        */

        return $disk->download(
            $book->pdf_path,
            $downloadName,
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }
}