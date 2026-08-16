<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audiobook;
use App\Models\CartItem;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Display the authenticated user's cart.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = CartItem::query()
            ->with([
                'book:id,uuid,title,slug,subtitle,author,cover_image,price,currency',

                'audiobook:id,uuid,book_id,description,cover_image,price,currency,status,duration_seconds,published_at',

                'audiobook.book:id,uuid,title,slug,subtitle,author,cover_image',
            ])
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        /*
         * Calculate the cart subtotal from the captured
         * cart-item prices.
         *
         * Do not calculate this from current product prices.
         * The cart contains price snapshots.
         */
        $subtotal = $items->sum(
            function (CartItem $item): float {
                return (float) $item->subtotal;
            }
        );

        return response()->json([
            'data' => $items
                ->map(
                    fn (CartItem $item) =>
                        $this->formatCartItem($item)
                )
                ->values(),

            'subtotal' => round($subtotal, 2),

            'total' => round($subtotal, 2),
        ]);
    }

    /**
     * Add a book or audiobook to the authenticated
     * user's cart.
     *
     * Exactly one of:
     *
     * book_uuid
     * audiobook_uuid
     *
     * must be supplied.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make(
            $request->all(),
            [
                'book_uuid' => [
                    'nullable',
                    'uuid',
                ],

                'audiobook_uuid' => [
                    'nullable',
                    'uuid',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bookUuid = $request->input('book_uuid');
        $audiobookUuid = $request->input('audiobook_uuid');

        /*
         * Exactly one product must be supplied.
         */
        if (
            ($bookUuid === null && $audiobookUuid === null)
            ||
            ($bookUuid !== null && $audiobookUuid !== null)
        ) {
            return response()->json([
                'message' => 'Please provide either a book UUID or an audiobook UUID.',

                'errors' => [
                    'book_uuid' => [
                        'Provide exactly one of book_uuid or audiobook_uuid.',
                    ],

                    'audiobook_uuid' => [
                        'Provide exactly one of book_uuid or audiobook_uuid.',
                    ],
                ],
            ], 422);
        }

        /*
         * Add a standard digital book.
         */
        if ($bookUuid !== null) {
            return $this->addBookToCart(
                $user->id,
                $bookUuid
            );
        }

        /*
         * Add an audiobook.
         */
        return $this->addAudiobookToCart(
            $user->id,
            $audiobookUuid
        );
    }

    /**
     * Remove an item from the authenticated user's cart.
     */
    public function destroy(
        Request $request,
        string $uuid
    ): JsonResponse {
        $user = $request->user();

        /*
         * Only allow the authenticated user to remove
         * their own cart item.
         */
        $cartItem = CartItem::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $cartItem->delete();

        return response()->json([
            'message' => 'Cart item removed successfully.',
        ]);
    }

    /**
     * Add a book to the customer's cart.
     */
    private function addBookToCart(
        int $userId,
        string $bookUuid
    ): JsonResponse {
        /*
         * Only published books can be purchased.
         */
        $book = Book::query()
            ->where('uuid', $bookUuid)
            ->where('is_published', true)
            ->first();

        if (! $book) {
            return response()->json([
                'message' => 'The selected book is not available for purchase.',

                'errors' => [
                    'book_uuid' => [
                        'The selected book is not available for purchase.',
                    ],
                ],
            ], 422);
        }

        /*
         * A customer who already owns an active,
         * readable entitlement cannot purchase
         * the same book again.
         */
        $alreadyOwnsBook = $book->entitlements()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('can_read', true)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now()
                    );
            })
            ->exists();

        if ($alreadyOwnsBook) {
            return response()->json([
                'message' => 'You already own this book.',

                'errors' => [
                    'book_uuid' => [
                        'You already own this book.',
                    ],
                ],
            ], 422);
        }

        /*
         * A book can only appear once in the customer's cart.
         */
        $existingCartItem = CartItem::query()
            ->where('user_id', $userId)
            ->where('item_type', 'book')
            ->where('book_id', $book->id)
            ->exists();

        if ($existingCartItem) {
            return response()->json([
                'message' => 'This book is already in your cart.',

                'errors' => [
                    'book_uuid' => [
                        'This book is already in your cart.',
                    ],
                ],
            ], 422);
        }

        /*
         * Capture the current price.
         *
         * The cart must preserve this price even if
         * the catalogue price changes later.
         */
        $unitPrice = round(
            (float) $book->price,
            2
        );

        $cartItem = CartItem::create([
            'user_id' => $userId,

            /*
             * IMPORTANT:
             * Identify this cart item as a book.
             */
            'item_type' => 'book',

            'book_id' => $book->id,

            'audiobook_id' => null,

            'unit_price' => $unitPrice,

            'currency' => $book->currency,

            'quantity' => 1,

            'subtotal' => $unitPrice,
        ]);

        /*
         * Load the relationship for the response.
         */
        $cartItem->load([
            'book:id,uuid,title,slug,subtitle,author,cover_image,price,currency',
        ]);

        return response()->json([
            'data' => $this->formatCartItem($cartItem),
        ], 201);
    }

    /**
     * Add an audiobook to the customer's cart.
     */
    private function addAudiobookToCart(
        int $userId,
        string $audiobookUuid
    ): JsonResponse {
        /*
         * Only active and currently published audiobooks
         * can be purchased.
         */
        $audiobook = Audiobook::query()
            ->with([
                'book:id,uuid,title,slug,subtitle,author,cover_image',
            ])
            ->where('uuid', $audiobookUuid)
            ->first();

        if (
            ! $audiobook
            || ! $audiobook->isPurchasable()
        ) {
            return response()->json([
                'message' => 'The selected audiobook is not available for purchase.',

                'errors' => [
                    'audiobook_uuid' => [
                        'The selected audiobook is not available for purchase.',
                    ],
                ],
            ], 422);
        }

        /*
         * A customer who already has an active audiobook
         * entitlement cannot purchase the same audiobook again.
         */
        $alreadyOwnsAudiobook = $audiobook->entitlements()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now()
                    );
            })
            ->exists();

        if ($alreadyOwnsAudiobook) {
            return response()->json([
                'message' => 'You already own this audiobook.',

                'errors' => [
                    'audiobook_uuid' => [
                        'You already own this audiobook.',
                    ],
                ],
            ], 422);
        }

        /*
         * An audiobook can only appear once in
         * the customer's cart.
         */
        $existingCartItem = CartItem::query()
            ->where('user_id', $userId)
            ->where('item_type', 'audiobook')
            ->where('audiobook_id', $audiobook->id)
            ->exists();

        if ($existingCartItem) {
            return response()->json([
                'message' => 'This audiobook is already in your cart.',

                'errors' => [
                    'audiobook_uuid' => [
                        'This audiobook is already in your cart.',
                    ],
                ],
            ], 422);
        }

        /*
         * Capture the audiobook price at the moment
         * it is added to the cart.
         */
        $unitPrice = round(
            (float) $audiobook->price,
            2
        );

        $cartItem = CartItem::create([
            'user_id' => $userId,

            /*
             * IMPORTANT:
             * Identify this cart item as an audiobook.
             */
            'item_type' => 'audiobook',

            'book_id' => null,

            'audiobook_id' => $audiobook->id,

            'unit_price' => $unitPrice,

            'currency' => $audiobook->currency,

            'quantity' => 1,

            'subtotal' => $unitPrice,
        ]);

        /*
         * Load the audiobook and its parent book.
         */
        $cartItem->load([
            'audiobook:id,uuid,book_id,description,cover_image,price,currency,status,duration_seconds,published_at',

            'audiobook.book:id,uuid,title,slug,subtitle,author,cover_image',
        ]);

        return response()->json([
            'data' => $this->formatCartItem($cartItem),
        ], 201);
    }

    /**
     * Format a cart item for the customer-facing API.
     *
     * IMPORTANT:
     * Private audiobook information such as the actual
     * audio_file path is never returned here.
     */
    private function formatCartItem(
        CartItem $cartItem
    ): array {
        $data = [
            'id' => $cartItem->id,

            'uuid' => $cartItem->uuid,

            /*
             * item_type is the canonical product type.
             */
            'type' => $cartItem->item_type,

            'quantity' => (int) $cartItem->quantity,

            'unit_price' => number_format(
                (float) $cartItem->unit_price,
                2,
                '.',
                ''
            ),

            'currency' => $cartItem->currency,

            'subtotal' => number_format(
                (float) $cartItem->subtotal,
                2,
                '.',
                ''
            ),

            'book' => null,

            'audiobook' => null,
        ];

        /*
         * Standard digital book.
         */
        if (
            $cartItem->item_type === 'book'
            && $cartItem->book
        ) {
            $book = $cartItem->book;

            $data['book'] = [
                'id' => $book->id,

                'uuid' => $book->uuid,

                'title' => $book->title,

                'slug' => $book->slug,

                'subtitle' => $book->subtitle,

                'author' => $book->author,

                'cover_image' => $book->cover_image,

                'price' => number_format(
                    (float) $book->price,
                    2,
                    '.',
                    ''
                ),

                'currency' => $book->currency,
            ];
        }

        /*
         * Audiobook.
         *
         * Deliberately do NOT expose audio_file.
         */
        if (
            $cartItem->item_type === 'audiobook'
            && $cartItem->audiobook
        ) {
            $audiobook = $cartItem->audiobook;

            $book = $audiobook->book;

            $data['audiobook'] = [
                'id' => $audiobook->id,

                'uuid' => $audiobook->uuid,

                'title' => $book?->title,

                'slug' => $book?->slug,

                'subtitle' => $book?->subtitle,

                'author' => $book?->author,

                'description' => $audiobook->description,

                'cover_image' => $audiobook->cover_image
                    ?? $book?->cover_image,

                'price' => number_format(
                    (float) $audiobook->price,
                    2,
                    '.',
                    ''
                ),

                'currency' => $audiobook->currency,

                'duration_seconds' =>
                    $audiobook->duration_seconds,

                'duration_minutes' =>
                    $audiobook->durationInMinutes(),

                'status' => $audiobook->status,

                'published_at' =>
                    $audiobook->published_at,
            ];
        }

        return $data;
    }
}