<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudiobookEntitlement;
use App\Models\BookEntitlement;
use App\Models\Bundle;
use App\Models\CartItem;
use App\Models\CourseEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {
    }

    /**
     * Display the authenticated user's checkout summary.
     *
     * This does not create an order.
     *
     * Supports:
     * - Books
     * - Audiobooks
     * - Courses
     * - Bundles
     * - Mixed carts
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
                    'subtotal' => '0.00',
                    'discount' => '0.00',
                    'total' => '0.00',
                    'coupon' => null,
                ],
            ], 422);
        }

        $this->ensureSingleCurrency($cartItems);

        foreach ($cartItems as $cartItem) {
            $this->validateCartItem(
                $cartItem,
                $user->id
            );
        }

        $subtotal = $this->calculateSubtotal($cartItems);

        return response()->json([
            'data' => [
                'items' => $cartItems
                    ->map(
                        fn (CartItem $item) =>
                        $this->formatCartItem($item)
                    )
                    ->values(),

                'currency' => strtoupper(
                    $cartItems->first()->currency
                ),

                'subtotal' => $this->formatMoney(
                    $subtotal
                ),

                'discount' => $this->formatMoney(0),

                'total' => $this->formatMoney(
                    $subtotal
                ),

                'coupon' => null,
            ],
        ]);
    }

    /**
     * Create a pending order from the authenticated
     * user's cart.
     *
     * Payment is NOT processed here.
     *
     * If a coupon code is supplied, it is validated
     * and applied after the order items have been created.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => [
                'nullable',
                'string',
                'max:50',
            ],
        ]);

        $user = $request->user();

        $couponCode = isset($validated['coupon_code'])
            ? trim($validated['coupon_code'])
            : null;

        try {
            $order = DB::transaction(function () use (
                $user,
                $couponCode
            ) {
                /*
                 * Load the user's current cart.
                 */
                $cartItems = $this->getValidCartItems(
                    $user->id
                );

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
                 * All products in one order must use
                 * the same currency.
                 */
                $this->ensureSingleCurrency(
                    $cartItems
                );

                /*
                 * Validate every product again immediately
                 * before creating the order.
                 */
                foreach ($cartItems as $cartItem) {
                    $this->validateCartItem(
                        $cartItem,
                        $user->id
                    );
                }

                /*
                 * Calculate the order subtotal from the
                 * captured cart prices.
                 */
                $subtotal = $this->calculateSubtotal(
                    $cartItems
                );

                /*
                 * Create the pending order.
                 *
                 * The initial discount is zero. If a coupon
                 * is supplied, CouponService will update the
                 * discount and total after order items exist.
                 */
                $order = Order::create([
                    'user_id' => $user->id,

                    'order_number' =>
                        $this->generateOrderNumber(),

                    'status' => 'pending',

                    'payment_status' => 'unpaid',

                    'currency' => strtoupper(
                        $cartItems->first()->currency
                    ),

                    'subtotal' => $subtotal,

                    'discount' => 0.00,

                    'total' => $subtotal,

                    'paid_at' => null,
                ]);

                /*
                 * Convert every cart item into an order item.
                 *
                 * IMPORTANT:
                 * The item type, product IDs and captured
                 * prices are preserved.
                 */
                foreach ($cartItems as $cartItem) {
                    OrderItem::create([
                        'order_id' => $order->id,

                        'item_type' =>
                            $cartItem->item_type,

                        'book_id' =>
                            $cartItem->book_id,

                        'audiobook_id' =>
                            $cartItem->audiobook_id,

                        'course_id' =>
                            $cartItem->course_id,

                        'bundle_id' =>
                            $cartItem->bundle_id,

                        'unit_price' =>
                            $cartItem->unit_price,

                        'currency' => strtoupper(
                            $cartItem->currency
                        ),

                        'quantity' =>
                            $cartItem->quantity,

                        'subtotal' =>
                            $cartItem->subtotal,
                    ]);
                }

                /*
                 * Apply the coupon only after the order items
                 * have been created.
                 *
                 * CouponService handles:
                 *
                 * - coupon validity
                 * - usage limits
                 * - per-user limits
                 * - minimum order amount
                 * - product restrictions
                 * - discount calculation
                 * - order discount
                 * - order total
                 * - coupon usage record
                 * - coupon usage count
                 */
                if (
                    $couponCode !== null
                    && $couponCode !== ''
                ) {
                    try {
                        $this->couponService->apply(
                            $couponCode,
                            $user,
                            $order
                        );
                    } catch (RuntimeException $exception) {
                        throw ValidationException::withMessages([
                            'coupon_code' => [
                                $exception->getMessage(),
                            ],
                        ]);
                    }
                }

                /*
                 * Clear the cart only after the order,
                 * order items and coupon processing have
                 * succeeded.
                 *
                 * If anything fails, the transaction rolls
                 * back and the cart remains intact.
                 */
                CartItem::query()
                    ->where('user_id', $user->id)
                    ->delete();

                /*
                 * Reload the order with all relationships
                 * required by the customer-facing response.
                 */
                $order->load([
                    'items.book:id,uuid,title,slug,subtitle,author,cover_image,price,currency',

                    'items.audiobook' => function ($query) {
                        $query->with([
                            'book:id,uuid,title,slug,subtitle,author,cover_image',
                        ]);
                    },

                    'items.course:id,uuid,title,slug,subtitle,description,cover_image,price,currency,status,published_at',

                    'items.bundle:id,uuid,name,slug,description,cover_image,price,currency,is_active,is_published,published_at',
                ]);

                /*
                 * Load the coupon usage relationship if the
                 * relationship exists on the Order model.
                 */
                if (
                    method_exists(
                        $order,
                        'couponUsages'
                    )
                ) {
                    $order->load('couponUsages');
                }

                return $order->fresh([
                    'items.book:id,uuid,title,slug,subtitle,author,cover_image,price,currency',

                    'items.audiobook' => function ($query) {
                        $query->with([
                            'book:id,uuid,title,slug,subtitle,author,cover_image',
                        ]);
                    },

                    'items.course:id,uuid,title,slug,subtitle,description,cover_image,price,currency,status,published_at',

                    'items.bundle:id,uuid,name,slug,description,cover_image,price,currency,is_active,is_published,published_at',
                ]);
            });

        } catch (ValidationException $exception) {
            throw $exception;

        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'coupon_code' => [
                    $exception->getMessage(),
                ],
            ]);
        }

        return response()->json([
            'message' =>
                'Checkout order created successfully.',

            'data' => $this->formatOrder($order),
        ], 201);
    }

    /**
     * Retrieve the authenticated user's cart items.
     */
    private function getValidCartItems(int $userId)
    {
        return CartItem::query()
            ->with([
                'book:id,uuid,title,slug,subtitle,author,cover_image,price,currency,is_published,published_at',

                'audiobook' => function ($query) {
                    $query->with([
                        'book:id,uuid,title,slug,subtitle,author,cover_image',
                    ]);
                },

                'course:id,uuid,title,slug,subtitle,description,cover_image,price,currency,status,published_at',

                'bundle:id,uuid,name,slug,description,cover_image,price,currency,is_active,is_published,published_at',
            ])
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Validate an individual cart item.
     *
     * Supports:
     * - Books
     * - Audiobooks
     * - Courses
     * - Bundles
     */
    private function validateCartItem(
        CartItem $cartItem,
        int $userId
    ): void {
        if ($cartItem->quantity < 1) {
            throw ValidationException::withMessages([
                'cart' => [
                    'Invalid quantity in your cart.',
                ],
            ]);
        }

        if ((float) $cartItem->unit_price < 0) {
            throw ValidationException::withMessages([
                'cart' => [
                    'Invalid price in your cart.',
                ],
            ]);
        }

        if (! $cartItem->currency) {
            throw ValidationException::withMessages([
                'cart' => [
                    'Invalid currency in your cart.',
                ],
            ]);
        }

        if ($cartItem->isBook()) {
            $this->validateBookCartItem(
                $cartItem,
                $userId
            );

            return;
        }

        if ($cartItem->isAudiobook()) {
            $this->validateAudiobookCartItem(
                $cartItem,
                $userId
            );

            return;
        }

        if ($cartItem->isCourse()) {
            $this->validateCourseCartItem(
                $cartItem,
                $userId
            );

            return;
        }

        if ($cartItem->isBundle()) {
            $this->validateBundleCartItem(
                $cartItem
            );

            return;
        }

        throw ValidationException::withMessages([
            'cart' => [
                'Your cart contains an invalid product type.',
            ],
        ]);
    }

    /**
     * Validate a book cart item.
     */
    private function validateBookCartItem(
        CartItem $cartItem,
        int $userId
    ): void {
        $book = $cartItem->book;

        if (
            ! $book
            || ! $book->is_published
            || (
                $book->published_at !== null
                && $book->published_at->isFuture()
            )
        ) {
            throw ValidationException::withMessages([
                'cart' => [
                    sprintf(
                        "The book '%s' is no longer available for purchase.",
                        $book?->title ?? 'Unknown book'
                    ),
                ],
            ]);
        }

        $alreadyOwnsBook = BookEntitlement::query()
            ->where('user_id', $userId)
            ->where('book_id', $book->id)
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
            throw ValidationException::withMessages([
                'cart' => [
                    "You already own the book '{$book->title}'.",
                ],
            ]);
        }
    }

    /**
     * Validate an audiobook cart item.
     */
    private function validateAudiobookCartItem(
        CartItem $cartItem,
        int $userId
    ): void {
        $audiobook = $cartItem->audiobook;

        if (! $audiobook) {
            throw ValidationException::withMessages([
                'cart' => [
                    'The audiobook in your cart no longer exists.',
                ],
            ]);
        }

        if (! $audiobook->isActive()) {
            throw ValidationException::withMessages([
                'cart' => [
                    'The audiobook is no longer available for purchase.',
                ],
            ]);
        }

        if (! $audiobook->isPurchasable()) {
            throw ValidationException::withMessages([
                'cart' => [
                    'The audiobook is not currently available for purchase.',
                ],
            ]);
        }

        $alreadyOwnsAudiobook =
            AudiobookEntitlement::query()
                ->where('user_id', $userId)
                ->where(
                    'audiobook_id',
                    $audiobook->id
                )
                ->where('status', 'active')
                ->where('can_stream', true)
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
            throw ValidationException::withMessages([
                'cart' => [
                    'You already own this audiobook.',
                ],
            ]);
        }
    }

    /**
     * Validate a course cart item.
     */
    private function validateCourseCartItem(
        CartItem $cartItem,
        int $userId
    ): void {
        $course = $cartItem->course;

        if (! $course) {
            throw ValidationException::withMessages([
                'cart' => [
                    'The course in your cart no longer exists.',
                ],
            ]);
        }

        if (! $course->isActive()) {
            throw ValidationException::withMessages([
                'cart' => [
                    "The course '{$course->title}' is no longer available for purchase.",
                ],
            ]);
        }

        if (! $course->isPurchasable()) {
            throw ValidationException::withMessages([
                'cart' => [
                    "The course '{$course->title}' is not currently available for purchase.",
                ],
            ]);
        }

        $alreadyOwnsCourse =
            CourseEntitlement::query()
                ->where('user_id', $userId)
                ->where('course_id', $course->id)
                ->where('status', 'active')
                ->where('can_access', true)
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

        if ($alreadyOwnsCourse) {
            throw ValidationException::withMessages([
                'cart' => [
                    "You already have access to the course '{$course->title}'.",
                ],
            ]);
        }
    }

    /**
     * Validate a bundle cart item.
     */
    private function validateBundleCartItem(
        CartItem $cartItem
    ): void {
        $bundle = $cartItem->bundle;

        if (! $bundle) {
            throw ValidationException::withMessages([
                'cart' => [
                    'The bundle in your cart no longer exists.',
                ],
            ]);
        }

        if (! $bundle->isActive()) {
            throw ValidationException::withMessages([
                'cart' => [
                    "The bundle '{$bundle->name}' is no longer available for purchase.",
                ],
            ]);
        }

        if (! $bundle->isPurchasable()) {
            throw ValidationException::withMessages([
                'cart' => [
                    "The bundle '{$bundle->name}' is not currently available for purchase.",
                ],
            ]);
        }

        /*
         * The bundle price captured in the cart is intentionally
         * preserved. We do not replace it with the current
         * catalogue price during checkout.
         */
        if ((float) $cartItem->unit_price < 0) {
            throw ValidationException::withMessages([
                'cart' => [
                    "The bundle '{$bundle->name}' has an invalid price.",
                ],
            ]);
        }
    }

    /**
     * Ensure all cart items use one currency.
     */
    private function ensureSingleCurrency(
        $cartItems
    ): void {
        $currencies = $cartItems
            ->pluck('currency')
            ->filter()
            ->map(
                fn ($currency) =>
                strtoupper(trim($currency))
            )
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
     *
     * Uses the captured cart-item subtotal rather
     * than the current catalogue price.
     */
    private function calculateSubtotal(
        $cartItems
    ): float {
        return round(
            $cartItems->sum(
                function (CartItem $item) {
                    return (float) $item->subtotal;
                }
            ),
            2
        );
    }

    /**
     * Generate a human-readable order number.
     *
     * Example:
     *
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
                ->where(
                    'order_number',
                    $orderNumber
                )
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
    private function formatCartItem(
        CartItem $cartItem
    ): array {
        $data = [
            'id' => $cartItem->id,

            'uuid' => $cartItem->uuid,

            'item_type' => $cartItem->item_type,

            'quantity' => (int) $cartItem->quantity,

            'unit_price' => $this->formatMoney(
                $cartItem->unit_price
            ),

            'currency' => strtoupper(
                $cartItem->currency
            ),

            'subtotal' => $this->formatMoney(
                $cartItem->subtotal
            ),

            'book' => null,

            'audiobook' => null,

            'course' => null,

            'bundle' => null,
        ];

        if ($cartItem->isBook() && $cartItem->book) {
            $book = $cartItem->book;

            $data['book'] = [
                'id' => $book->id,

                'uuid' => $book->uuid,

                'title' => $book->title,

                'slug' => $book->slug,

                'subtitle' => $book->subtitle,

                'author' => $book->author,

                'cover_image' => $book->cover_image,

                'price' => $this->formatMoney(
                    $book->price
                ),

                'currency' => strtoupper(
                    $book->currency
                ),
            ];
        }

        if (
            $cartItem->isAudiobook()
            && $cartItem->audiobook
        ) {
            $audiobook = $cartItem->audiobook;

            $data['audiobook'] = [
                'id' => $audiobook->id,

                'uuid' => $audiobook->uuid,

                'book_id' => $audiobook->book_id,

                'title' =>
                    $audiobook->book?->title,

                'slug' =>
                    $audiobook->book?->slug,

                'subtitle' =>
                    $audiobook->book?->subtitle,

                'author' =>
                    $audiobook->book?->author,

                'description' =>
                    $audiobook->description
                    ?? $audiobook->book?->description,

                'cover_image' =>
                    $audiobook->cover_image
                    ?? $audiobook->book?->cover_image,

                'price' => $this->formatMoney(
                    $audiobook->price
                ),

                'currency' => strtoupper(
                    $audiobook->currency
                ),

                'duration_seconds' =>
                    $audiobook->duration_seconds,

                'duration_minutes' =>
                    $audiobook->durationInMinutes(),
            ];
        }

        if (
            $cartItem->isCourse()
            && $cartItem->course
        ) {
            $course = $cartItem->course;

            $data['course'] = [
                'id' => $course->id,

                'uuid' => $course->uuid,

                'title' => $course->title,

                'slug' => $course->slug,

                'subtitle' => $course->subtitle,

                'description' => $course->description,

                'cover_image' => $course->cover_image,

                'price' => $this->formatMoney(
                    $course->price
                ),

                'currency' => strtoupper(
                    $course->currency
                ),
            ];
        }

        if (
            $cartItem->isBundle()
            && $cartItem->bundle
        ) {
            $bundle = $cartItem->bundle;

            $data['bundle'] = [
                'id' => $bundle->id,

                'uuid' => $bundle->uuid,

                'name' => $bundle->name,

                'slug' => $bundle->slug,

                'description' => $bundle->description,

                'cover_image' => $bundle->cover_image,

                'price' => $this->formatMoney(
                    $bundle->price
                ),

                'currency' => strtoupper(
                    $bundle->currency
                ),

                'is_active' => (bool) $bundle->is_active,

                'is_published' =>
                    (bool) $bundle->is_published,

                'published_at' =>
                    $bundle->published_at?->toISOString(),
            ];
        }

        return $data;
    }

    /**
     * Format the created order for the API.
     */
    private function formatOrder(
        Order $order
    ): array {
        return [
            'id' => $order->id,

            'uuid' => $order->uuid,

            'order_number' =>
                $order->order_number,

            'status' =>
                $order->status,

            'payment_status' =>
                $order->payment_status,

            'currency' =>
                strtoupper($order->currency),

            'subtotal' =>
                $this->formatMoney(
                    $order->subtotal
                ),

            'discount' =>
                $this->formatMoney(
                    $order->discount
                ),

            'total' =>
                $this->formatMoney(
                    $order->total
                ),

            'paid_at' =>
                $order->paid_at?->toISOString(),

            'items' => $order->items
                ->map(
                    function (OrderItem $item) {
                        return $this->formatOrderItem(
                            $item
                        );
                    }
                )
                ->values(),
        ];
    }

    /**
     * Format an individual order item.
     */
    private function formatOrderItem(
        OrderItem $item
    ): array {
        $data = [
            'id' => $item->id,

            'uuid' => $item->uuid,

            'item_type' =>
                $item->item_type,

            'quantity' =>
                (int) $item->quantity,

            'unit_price' =>
                $this->formatMoney(
                    $item->unit_price
                ),

            'currency' =>
                strtoupper(
                    $item->currency
                ),

            'subtotal' =>
                $this->formatMoney(
                    $item->subtotal
                ),

            'book' => null,

            'audiobook' => null,

            'course' => null,

            'bundle' => null,
        ];

        if ($item->isBook() && $item->book) {
            $book = $item->book;

            $data['book'] = [
                'id' => $book->id,

                'uuid' => $book->uuid,

                'title' => $book->title,

                'slug' => $book->slug,

                'subtitle' => $book->subtitle,

                'author' => $book->author,

                'cover_image' =>
                    $book->cover_image,
            ];
        }

        if (
            $item->isAudiobook()
            && $item->audiobook
        ) {
            $audiobook = $item->audiobook;

            $data['audiobook'] = [
                'id' => $audiobook->id,

                'uuid' => $audiobook->uuid,

                'book_id' =>
                    $audiobook->book_id,

                'title' =>
                    $audiobook->book?->title,

                'slug' =>
                    $audiobook->book?->slug,

                'subtitle' =>
                    $audiobook->book?->subtitle,

                'author' =>
                    $audiobook->book?->author,

                'description' =>
                    $audiobook->description
                    ?? $audiobook->book?->description,

                'cover_image' =>
                    $audiobook->cover_image
                    ?? $audiobook->book?->cover_image,

                'duration_seconds' =>
                    $audiobook->duration_seconds,

                'duration_minutes' =>
                    $audiobook->durationInMinutes(),
            ];
        }

        if (
            $item->isCourse()
            && $item->course
        ) {
            $course = $item->course;

            $data['course'] = [
                'id' => $course->id,

                'uuid' => $course->uuid,

                'title' => $course->title,

                'slug' => $course->slug,

                'subtitle' => $course->subtitle,

                'description' => $course->description,

                'cover_image' =>
                    $course->cover_image,
            ];
        }

        if (
            $item->isBundle()
            && $item->bundle
        ) {
            $bundle = $item->bundle;

            $data['bundle'] = [
                'id' => $bundle->id,

                'uuid' => $bundle->uuid,

                'name' => $bundle->name,

                'slug' => $bundle->slug,

                'description' =>
                    $bundle->description,

                'cover_image' =>
                    $bundle->cover_image,

                'price' => $this->formatMoney(
                    $bundle->price
                ),

                'currency' => strtoupper(
                    $bundle->currency
                ),

                'is_active' =>
                    (bool) $bundle->is_active,

                'is_published' =>
                    (bool) $bundle->is_published,

                'published_at' =>
                    $bundle->published_at?->toISOString(),
            ];
        }

        return $data;
    }

    /**
     * Format money consistently.
     */
    private function formatMoney(
        $value
    ): string {
        return number_format(
            (float) $value,
            2,
            '.',
            ''
        );
    }
}