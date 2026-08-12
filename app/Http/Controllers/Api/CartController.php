<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\CartItem;
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
            ])
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        /*
         * Calculate the cart subtotal from the captured
         * cart-item prices, not from the current book prices.
         */
        $subtotal = $items->sum(function (CartItem $item): float {
            return (float) $item->subtotal;
        });

        return response()->json([
            'data' => $items
                ->map(fn (CartItem $item) => $this->formatCartItem($item))
                ->values(),

            /*
             * Cart totals are returned as numeric values.
             *
             * Do not use number_format() here because
             * number_format() returns a string.
             *
             * Example:
             *     0
             * instead of:
             *     "0.00"
             */
            'subtotal' => round($subtotal, 2),

            'total' => round($subtotal, 2),
        ]);
    }

    /**
     * Add a published book to the authenticated user's cart.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
         * Validate the submitted book UUID.
         */
        $validator = Validator::make(
            $request->all(),
            [
                'book_uuid' => [
                    'required',
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

        /*
         * Only published books can be added to the cart.
         *
         * Soft-deleted books are automatically excluded
         * by Laravel's SoftDeletes global scope.
         */
        $book = Book::query()
            ->where('uuid', $request->input('book_uuid'))
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
         * readable entitlement cannot purchase the
         * same book again.
         *
         * Expired, revoked, or inactive entitlements
         * do not count as active ownership.
         */
        $alreadyOwnsBook = $book->entitlements()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('can_read', true)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
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
         * A digital book can only appear once
         * in the customer's cart.
         */
        $existingCartItem = CartItem::query()
            ->where('user_id', $user->id)
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
         * Capture the current book price.
         *
         * This is important because the book price may
         * change after the customer adds it to the cart.
         */
        $unitPrice = round((float) $book->price, 2);

        $cartItem = CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => $unitPrice,
            'currency' => $book->currency,
            'quantity' => 1,
            'subtotal' => $unitPrice,
        ]);

        /*
         * Load the book relationship for the response.
         */
        $cartItem->load([
            'book:id,uuid,title,slug,subtitle,author,cover_image,price,currency',
        ]);

        return response()->json([
            'data' => $this->formatCartItem($cartItem),
        ], 201);
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
         * Restrict the lookup to the authenticated user.
         *
         * This prevents one customer from deleting
         * another customer's cart item.
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
     * Format a cart item for the customer-facing API.
     *
     * IMPORTANT:
     * The cart tests expect monetary values on individual
     * cart items to be formatted as strings with two decimals.
     *
     * Example:
     *     "20.00"
     *
     * rather than:
     *     20
     */
    private function formatCartItem(CartItem $cartItem): array
    {
        $book = $cartItem->book;

        return [
            'id' => $cartItem->id,
            'uuid' => $cartItem->uuid,

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

            'book' => [
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
            ],
        ];
    }
}