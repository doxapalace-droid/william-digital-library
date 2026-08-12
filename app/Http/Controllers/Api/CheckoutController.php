<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookEntitlement;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
 * Display the authenticated user's checkout summary.
 *
 * This does not create an order.
 */
public function index(Request $request): JsonResponse
{
    $user = $request->user();

    $cartItems = $this->getValidCartItems($user->id);

    if ($cartItems->isEmpty()) {
        return response()->json([
            'message' => 'Your cart is empty.',
            'data' => [
                'items' => [],
                'currency' => null,
                'subtotal' => 0.00,
                'discount' => 0.00,
                'total' => 0.00,
            ],
        ], 422);
    }

    $this->ensureSingleCurrency($cartItems);

    $subtotal = $this->calculateSubtotal($cartItems);

    return response()->json([
        'data' => [
            'items' => $cartItems
                ->map(fn (CartItem $item) => $this->formatCartItem($item))
                ->values(),

            'currency' => $cartItems->first()->currency,

            'subtotal' => number_format($subtotal, 2, '.', ''),

            'discount' => number_format(0, 2, '.', ''),

            'total' => number_format($subtotal, 2, '.', ''),
        ],
    ]);
    }

    /**
     * Create a pending order from the authenticated user's cart.
     *
     * Payment is NOT processed here.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $order = DB::transaction(function () use ($user) {

            /*
             * Load the user's current cart.
             */
            $cartItems = $this->getValidCartItems($user->id);

            /*
             * Checkout cannot proceed with an empty cart.
             */
            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => [
                        'Your cart is empty.',
                    ],
                ]);
            }

            /*
             * All items in one order must use
             * the same currency.
             */
            $this->ensureSingleCurrency($cartItems);

            /*
             * Make sure every book is still available
             * and the customer still has the right to purchase it.
             */
            foreach ($cartItems as $cartItem) {
                $this->validateCartItem($cartItem, $user->id);
            }

            /*
             * Calculate the order totals from the
             * captured cart prices.
             *
             * We deliberately use unit_price from the
             * cart rather than the current book price.
             */
            $subtotal = $this->calculateSubtotal($cartItems);

            $discount = 0.00;

            $total = round($subtotal - $discount, 2);

            /*
             * Create the pending order.
             */
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => $cartItems->first()->currency,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid_at' => null,
            ]);

            /*
             * Convert every cart item into an order item.
             *
             * Order items preserve the price at checkout.
             */
            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id' => $cartItem->book_id,
                    'unit_price' => $cartItem->unit_price,
                    'currency' => $cartItem->currency,
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $cartItem->subtotal,
                ]);
            }

            /*
             * Load the relationships required by the
             * customer-facing response.
             */
            $order->load([
                'items.book:id,uuid,title,slug,subtitle,author,cover_image,price,currency',
            ]);

            return $order;
        });

        return response()->json([
            'message' => 'Checkout order created successfully.',
            'data' => $this->formatOrder($order),
        ], 201);
    }


    /**
     * Retrieve the user's cart items together with their books.
     */
    private function getValidCartItems(int $userId)
    {
        return CartItem::query()
            ->with([
                'book:id,uuid,title,slug,subtitle,author,cover_image,price,currency,is_published',
            ])
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->get();
    }


    /**
     * Validate an individual cart item before checkout.
     */
    private function validateCartItem(
        CartItem $cartItem,
        int $userId
    ): void {
        $book = $cartItem->book;

        /*
         * The book may have been unpublished after
         * it was added to the cart.
         */
        if (! $book || ! $book->is_published) {
            throw ValidationException::withMessages([
                'cart' => [
                    "The book '{$book?->title}' is no longer available for purchase.",
                ],
            ]);
        }

        /*
         * A customer cannot purchase a book they
         * already own.
         */
        $alreadyOwnsBook = BookEntitlement::query()
            ->where('user_id', $userId)
            ->where('book_id', $book->id)
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
            throw ValidationException::withMessages([
                'cart' => [
                    "You already own the book '{$book->title}'.",
                ],
            ]);
        }

        /*
         * Make sure the cart item has a valid quantity.
         */
        if ($cartItem->quantity < 1) {
            throw ValidationException::withMessages([
                'cart' => [
                    "Invalid quantity for '{$book->title}'.",
                ],
            ]);
        }

        /*
         * Make sure the captured price is valid.
         */
        if ((float) $cartItem->unit_price < 0) {
            throw ValidationException::withMessages([
                'cart' => [
                    "Invalid price for '{$book->title}'.",
                ],
            ]);
        }
    }


    /**
     * Ensure all cart items use one currency.
     */
    private function ensureSingleCurrency($cartItems): void
    {
        $currencies = $cartItems
            ->pluck('currency')
            ->filter()
            ->map(fn ($currency) => strtoupper($currency))
            ->unique()
            ->values();

        if ($currencies->count() > 1) {
            throw ValidationException::withMessages([
                'cart' => [
                    'All items in your cart must use the same currency.',
                ],
            ]);
        }
    }


    /**
     * Calculate the cart subtotal.
     */
    private function calculateSubtotal($cartItems): float
    {
        return round(
            $cartItems->sum(function (CartItem $item) {
                return (float) $item->subtotal;
            }),
            2
        );
    }


    /**
     * Generate a human-readable order number.
     *
     * Example:
     * DP-000001
     */
    private function generateOrderNumber(): string
    {
        $nextId = ((int) Order::max('id')) + 1;

        do {
            $orderNumber = 'DP-' . str_pad(
                (string) $nextId,
                6,
                '0',
                STR_PAD_LEFT
            );

            $exists = Order::query()
                ->where('order_number', $orderNumber)
                ->exists();

            if ($exists) {
                $nextId++;
            }
        } while ($exists);

        return $orderNumber;
    }


    /**
     * Format a cart item for checkout.
     */
    private function formatCartItem(CartItem $cartItem): array
    {
        $book = $cartItem->book;

        return [
            'id' => $cartItem->id,
            'uuid' => $cartItem->uuid,

            'quantity' => $cartItem->quantity,

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


    /**
     * Format the created order for the API.
     */
    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,

            'status' => $order->status,
            'payment_status' => $order->payment_status,

            'currency' => $order->currency,

            'subtotal' => number_format(
                (float) $order->subtotal,
                2,
                '.',
                ''
            ),

            'discount' => number_format(
                (float) $order->discount,
                2,
                '.',
                ''
            ),

            'total' => number_format(
                (float) $order->total,
                2,
                '.',
                ''
            ),

            'paid_at' => $order->paid_at?->toISOString(),

            'items' => $order->items
                ->map(function (OrderItem $item) {
                    return [
                        'id' => $item->id,
                        'uuid' => $item->uuid,
                        'quantity' => $item->quantity,

                        'unit_price' => number_format(
                            (float) $item->unit_price,
                            2,
                            '.',
                            ''
                        ),

                        'currency' => $item->currency,

                        'subtotal' => number_format(
                            (float) $item->subtotal,
                            2,
                            '.',
                            ''
                        ),

                        'book' => [
                            'id' => $item->book->id,
                            'uuid' => $item->book->uuid,
                            'title' => $item->book->title,
                            'slug' => $item->book->slug,
                            'subtitle' => $item->book->subtitle,
                            'author' => $item->book->author,
                            'cover_image' => $item->book->cover_image,
                        ],
                    ];
                })
                ->values(),
        ];
    }
}