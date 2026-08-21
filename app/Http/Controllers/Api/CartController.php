<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Bundle;
use App\Models\CartItem;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Display the authenticated user's cart.
     *
     * Supported products:
     *
     * - books
     * - audiobooks
     * - courses
     * - bundles
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = CartItem::query()
            ->with([
                'book:id,uuid,title,slug,subtitle,author,cover_image,price,currency',

                'audiobook:id,uuid,book_id,description,cover_image,price,currency,status,duration_seconds,published_at',

                'audiobook.book:id,uuid,title,slug,subtitle,author,cover_image',

                'course:id,uuid,title,slug,subtitle,description,cover_image,price,currency,status,published_at',

                'bundle:id,uuid,name,slug,description,cover_image,price,currency,is_active,is_published,published_at',
            ])
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        /*
         * Cart prices were captured when each item
         * was added. Do not recalculate them from
         * the current catalogue prices.
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
     * Add a digital product to the authenticated
     * user's cart.
     *
     * Supported request fields:
     *
     * book_uuid
     * audiobook_uuid
     * course_uuid
     * course_id
     * bundle_uuid
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

                'course_uuid' => [
                    'nullable',
                    'uuid',
                ],

                /*
                 * Backwards-compatible course field.
                 *
                 * The existing frontend/tests may use
                 * course_id to carry the course UUID.
                 */
                'course_id' => [
                    'nullable',
                    'uuid',
                ],

                'bundle_uuid' => [
                    'nullable',
                    'uuid',
                ],

                'item_type' => [
                    'nullable',
                    'string',
                    'in:book,audiobook,course,bundle',
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

        $audiobookUuid = $request->input(
            'audiobook_uuid'
        );

        /*
         * Prefer course_uuid.
         *
         * Fall back to course_id for backwards
         * compatibility.
         */
        $courseUuid = $request->input('course_uuid')
            ?? $request->input('course_id');

        $bundleUuid = $request->input(
            'bundle_uuid'
        );

        /*
         * Do not allow both course identifiers.
         */
        if (
            $request->filled('course_uuid')
            && $request->filled('course_id')
        ) {
            return response()->json([
                'message' =>
                    'Provide only one course identifier.',

                'errors' => [
                    'course_uuid' => [
                        'Do not provide both course_uuid and course_id.',
                    ],

                    'course_id' => [
                        'Do not provide both course_uuid and course_id.',
                    ],
                ],
            ], 422);
        }

        /*
         * Exactly one product must be supplied.
         */
        $providedProducts = collect([
            $bookUuid,
            $audiobookUuid,
            $courseUuid,
            $bundleUuid,
        ])
            ->filter(
                fn ($value) =>
                    $value !== null
                    && $value !== ''
            )
            ->count();

        if ($providedProducts !== 1) {
            return response()->json([
                'message' =>
                    'Please provide exactly one product UUID.',

                'errors' => [
                    'book_uuid' => [
                        'Provide exactly one of book_uuid, audiobook_uuid, course_uuid, or bundle_uuid.',
                    ],

                    'audiobook_uuid' => [
                        'Provide exactly one of book_uuid, audiobook_uuid, course_uuid, or bundle_uuid.',
                    ],

                    'course_uuid' => [
                        'Provide exactly one of book_uuid, audiobook_uuid, course_uuid, or bundle_uuid.',
                    ],

                    'bundle_uuid' => [
                        'Provide exactly one of book_uuid, audiobook_uuid, course_uuid, or bundle_uuid.',
                    ],
                ],
            ], 422);
        }

        /*
         * Route the request to the appropriate
         * product handler.
         */
        if ($bookUuid !== null) {
            return $this->addBookToCart(
                $user->id,
                $bookUuid
            );
        }

        if ($audiobookUuid !== null) {
            return $this->addAudiobookToCart(
                $user->id,
                $audiobookUuid
            );
        }

        if ($courseUuid !== null) {
            return $this->addCourseToCart(
                $user->id,
                $courseUuid
            );
        }

        return $this->addBundleToCart(
            $user->id,
            $bundleUuid
        );
    }

    /**
     * Remove an item from the authenticated
     * user's cart.
     */
    public function destroy(
        Request $request,
        string $uuid
    ): JsonResponse {
        $user = $request->user();

        $cartItem = CartItem::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $cartItem->delete();

        return response()->json([
            'message' =>
                'Cart item removed successfully.',
        ]);
    }

    /**
     * Add a book to the cart.
     */
    private function addBookToCart(
        int $userId,
        string $bookUuid
    ): JsonResponse {
        $book = Book::query()
            ->where('uuid', $bookUuid)
            ->where('is_published', true)
            ->first();

        if (! $book) {
            return response()->json([
                'message' =>
                    'The selected book is not available for purchase.',

                'errors' => [
                    'book_uuid' => [
                        'The selected book is not available for purchase.',
                    ],
                ],
            ], 422);
        }

        /*
         * Prevent purchasing a book that the customer
         * already owns.
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
                'message' =>
                    'You already own this book.',

                'errors' => [
                    'book_uuid' => [
                        'You already own this book.',
                    ],
                ],
            ], 422);
        }

        /*
         * Prevent duplicate cart entries.
         */
        $existingCartItem = CartItem::query()
            ->where('user_id', $userId)
            ->where(
                'item_type',
                CartItem::TYPE_BOOK
            )
            ->where(
                'book_id',
                $book->id
            )
            ->exists();

        if ($existingCartItem) {
            return response()->json([
                'message' =>
                    'This book is already in your cart.',

                'errors' => [
                    'book_uuid' => [
                        'This book is already in your cart.',
                    ],
                ],
            ], 422);
        }

        $unitPrice = round(
            (float) $book->price,
            2
        );

        $cartItem = CartItem::create([
            'user_id' => $userId,

            'item_type' =>
                CartItem::TYPE_BOOK,

            'book_id' =>
                $book->id,

            'audiobook_id' => null,

            'course_id' => null,

            'bundle_id' => null,

            'unit_price' =>
                $unitPrice,

            'currency' =>
                strtoupper($book->currency),

            'quantity' => 1,

            'subtotal' =>
                $unitPrice,
        ]);

        $cartItem->load([
            'book:id,uuid,title,slug,subtitle,author,cover_image,price,currency',
        ]);

        return response()->json([
            'data' =>
                $this->formatCartItem($cartItem),
        ], 201);
    }

    /**
     * Add an audiobook to the cart.
     */
    private function addAudiobookToCart(
        int $userId,
        string $audiobookUuid
    ): JsonResponse {
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
                'message' =>
                    'The selected audiobook is not available for purchase.',

                'errors' => [
                    'audiobook_uuid' => [
                        'The selected audiobook is not available for purchase.',
                    ],
                ],
            ], 422);
        }

        /*
         * Prevent repurchasing an audiobook.
         */
        $alreadyOwnsAudiobook =
            $audiobook->entitlements()
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
                'message' =>
                    'You already own this audiobook.',

                'errors' => [
                    'audiobook_uuid' => [
                        'You already own this audiobook.',
                    ],
                ],
            ], 422);
        }

        /*
         * Prevent duplicate audiobook entries.
         */
        $existingCartItem = CartItem::query()
            ->where('user_id', $userId)
            ->where(
                'item_type',
                CartItem::TYPE_AUDIOBOOK
            )
            ->where(
                'audiobook_id',
                $audiobook->id
            )
            ->exists();

        if ($existingCartItem) {
            return response()->json([
                'message' =>
                    'This audiobook is already in your cart.',

                'errors' => [
                    'audiobook_uuid' => [
                        'This audiobook is already in your cart.',
                    ],
                ],
            ], 422);
        }

        $unitPrice = round(
            (float) $audiobook->price,
            2
        );

        $cartItem = CartItem::create([
            'user_id' => $userId,

            'item_type' =>
                CartItem::TYPE_AUDIOBOOK,

            'book_id' => null,

            'audiobook_id' =>
                $audiobook->id,

            'course_id' => null,

            'bundle_id' => null,

            'unit_price' =>
                $unitPrice,

            'currency' =>
                strtoupper($audiobook->currency),

            'quantity' => 1,

            'subtotal' =>
                $unitPrice,
        ]);

        $cartItem->load([
            'audiobook:id,uuid,book_id,description,cover_image,price,currency,status,duration_seconds,published_at',

            'audiobook.book:id,uuid,title,slug,subtitle,author,cover_image',
        ]);

        return response()->json([
            'data' =>
                $this->formatCartItem($cartItem),
        ], 201);
    }

    /**
     * Add a course to the cart.
     */
    private function addCourseToCart(
        int $userId,
        string $courseUuid
    ): JsonResponse {
        $course = Course::query()
            ->where('uuid', $courseUuid)
            ->first();

        if (
            ! $course
            || ! $course->isPurchasable()
        ) {
            return response()->json([
                'message' =>
                    'The selected course is not available for purchase.',

                'errors' => [
                    'course_uuid' => [
                        'The selected course is not available for purchase.',
                    ],
                ],
            ], 422);
        }

        /*
         * Prevent repurchasing a course.
         */
        $alreadyOwnsCourse =
            $course->entitlements()
                ->where('user_id', $userId)
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
            return response()->json([
                'message' =>
                    'You already own this course.',

                'errors' => [
                    'course_uuid' => [
                        'You already own this course.',
                    ],
                ],
            ], 422);
        }

        /*
         * Prevent duplicate course entries.
         */
        $existingCartItem = CartItem::query()
            ->where('user_id', $userId)
            ->where(
                'item_type',
                CartItem::TYPE_COURSE
            )
            ->where(
                'course_id',
                $course->id
            )
            ->exists();

        if ($existingCartItem) {
            return response()->json([
                'message' =>
                    'This course is already in your cart.',

                'errors' => [
                    'course_uuid' => [
                        'This course is already in your cart.',
                    ],
                ],
            ], 422);
        }

        $unitPrice = round(
            (float) $course->price,
            2
        );

        $cartItem = CartItem::create([
            'user_id' => $userId,

            'item_type' =>
                CartItem::TYPE_COURSE,

            'book_id' => null,

            'audiobook_id' => null,

            'course_id' =>
                $course->id,

            'bundle_id' => null,

            'unit_price' =>
                $unitPrice,

            'currency' =>
                strtoupper($course->currency),

            'quantity' => 1,

            'subtotal' =>
                $unitPrice,
        ]);

        $cartItem->load([
            'course:id,uuid,title,slug,subtitle,description,cover_image,price,currency,status,published_at',
        ]);

        return response()->json([
            'data' =>
                $this->formatCartItem($cartItem),
        ], 201);
    }

    /**
     * Add a bundle to the cart.
     */
    private function addBundleToCart(
        int $userId,
        string $bundleUuid
    ): JsonResponse {
        /*
         * Only purchasable bundles may enter
         * the customer's cart.
         */
        $bundle = Bundle::query()
            ->where('uuid', $bundleUuid)
            ->first();

        if (
            ! $bundle
            || ! $bundle->isPurchasable()
        ) {
            return response()->json([
                'message' =>
                    'The selected bundle is not available for purchase.',

                'errors' => [
                    'bundle_uuid' => [
                        'The selected bundle is not available for purchase.',
                    ],
                ],
            ], 422);
        }

        /*
         * Prevent duplicate bundle entries.
         */
        $existingCartItem = CartItem::query()
            ->where('user_id', $userId)
            ->where(
                'item_type',
                CartItem::TYPE_BUNDLE
            )
            ->where(
                'bundle_id',
                $bundle->id
            )
            ->exists();

        if ($existingCartItem) {
            return response()->json([
                'message' =>
                    'This bundle is already in your cart.',

                'errors' => [
                    'bundle_uuid' => [
                        'This bundle is already in your cart.',
                    ],
                ],
            ], 422);
        }

        /*
         * Capture the bundle price when it enters
         * the cart.
         */
        $unitPrice = round(
            (float) $bundle->price,
            2
        );

        $cartItem = CartItem::create([
            'user_id' => $userId,

            'item_type' =>
                CartItem::TYPE_BUNDLE,

            'book_id' => null,

            'audiobook_id' => null,

            'course_id' => null,

            'bundle_id' =>
                $bundle->id,

            'unit_price' =>
                $unitPrice,

            'currency' =>
                strtoupper($bundle->currency),

            'quantity' => 1,

            'subtotal' =>
                $unitPrice,
        ]);

        $cartItem->load([
            'bundle:id,uuid,name,slug,description,cover_image,price,currency,is_active,is_published,published_at',
        ]);

        return response()->json([
            'data' =>
                $this->formatCartItem($cartItem),
        ], 201);
    }

    /**
     * Format a cart item for the API.
     *
     * The response intentionally includes both:
     *
     * - the foreign-key IDs
     * - the expanded product object
     *
     * This makes the API convenient for both simple
     * clients and richer frontend interfaces.
     */
    private function formatCartItem(
        CartItem $cartItem
    ): array {
        $data = [
            'id' =>
                $cartItem->id,

            'uuid' =>
                $cartItem->uuid,

            'type' =>
                $cartItem->item_type,

            'item_type' =>
                $cartItem->item_type,

            /*
             * Product foreign keys.
             */
            'book_id' =>
                $cartItem->book_id,

            'audiobook_id' =>
                $cartItem->audiobook_id,

            'course_id' =>
                $cartItem->course_id,

            'bundle_id' =>
                $cartItem->bundle_id,

            'quantity' =>
                (int) $cartItem->quantity,

            /*
             * Price captured in the cart.
             */
            'unit_price' =>
                number_format(
                    (float) $cartItem->unit_price,
                    2,
                    '.',
                    ''
                ),

            'currency' =>
                strtoupper(
                    $cartItem->currency
                ),

            'subtotal' =>
                number_format(
                    (float) $cartItem->subtotal,
                    2,
                    '.',
                    ''
                ),

            /*
             * Expanded product data.
             */
            'book' => null,

            'audiobook' => null,

            'course' => null,

            'bundle' => null,
        ];

        /*
         * Book.
         */
        if (
            $cartItem->item_type
                === CartItem::TYPE_BOOK
            && $cartItem->book
        ) {
            $book = $cartItem->book;

            $data['book'] = [
                'id' =>
                    $book->id,

                'uuid' =>
                    $book->uuid,

                'title' =>
                    $book->title,

                'slug' =>
                    $book->slug,

                'subtitle' =>
                    $book->subtitle,

                'author' =>
                    $book->author,

                'cover_image' =>
                    $book->cover_image,

                'price' =>
                    number_format(
                        (float) $book->price,
                        2,
                        '.',
                        ''
                    ),

                'currency' =>
                    strtoupper(
                        $book->currency
                    ),
            ];
        }

        /*
         * Audiobook.
         *
         * audio_file is deliberately excluded.
         */
        if (
            $cartItem->item_type
                === CartItem::TYPE_AUDIOBOOK
            && $cartItem->audiobook
        ) {
            $audiobook =
                $cartItem->audiobook;

            $book =
                $audiobook->book;

            $data['audiobook'] = [
                'id' =>
                    $audiobook->id,

                'uuid' =>
                    $audiobook->uuid,

                'title' =>
                    $book?->title,

                'slug' =>
                    $book?->slug,

                'subtitle' =>
                    $book?->subtitle,

                'author' =>
                    $book?->author,

                'description' =>
                    $audiobook->description,

                'cover_image' =>
                    $audiobook->cover_image
                    ?? $book?->cover_image,

                'price' =>
                    number_format(
                        (float) $audiobook->price,
                        2,
                        '.',
                        ''
                    ),

                'currency' =>
                    strtoupper(
                        $audiobook->currency
                    ),

                'duration_seconds' =>
                    $audiobook->duration_seconds,

                'duration_minutes' =>
                    $audiobook->durationInMinutes(),

                'status' =>
                    $audiobook->status,

                'published_at' =>
                    $audiobook->published_at,
            ];
        }

        /*
         * Course.
         */
        if (
            $cartItem->item_type
                === CartItem::TYPE_COURSE
            && $cartItem->course
        ) {
            $course =
                $cartItem->course;

            $data['course'] = [
                'id' =>
                    $course->id,

                'uuid' =>
                    $course->uuid,

                'title' =>
                    $course->title,

                'slug' =>
                    $course->slug,

                'subtitle' =>
                    $course->subtitle,

                'description' =>
                    $course->description,

                'cover_image' =>
                    $course->cover_image,

                'price' =>
                    number_format(
                        (float) $course->price,
                        2,
                        '.',
                        ''
                    ),

                'currency' =>
                    strtoupper(
                        $course->currency
                    ),

                'status' =>
                    $course->status,

                'published_at' =>
                    $course->published_at,
            ];
        }

        /*
         * Bundle.
         */
        if (
            $cartItem->item_type
                === CartItem::TYPE_BUNDLE
            && $cartItem->bundle
        ) {
            $bundle =
                $cartItem->bundle;

            $data['bundle'] = [
                'id' =>
                    $bundle->id,

                'uuid' =>
                    $bundle->uuid,

                'name' =>
                    $bundle->name,

                'slug' =>
                    $bundle->slug,

                'description' =>
                    $bundle->description,

                'cover_image' =>
                    $bundle->cover_image,

                'price' =>
                    number_format(
                        (float) $bundle->price,
                        2,
                        '.',
                        ''
                    ),

                'currency' =>
                    strtoupper(
                        $bundle->currency
                    ),

                'is_active' =>
                    (bool) $bundle->is_active,

                'is_published' =>
                    (bool) $bundle->is_published,

                'published_at' =>
                    $bundle->published_at,
            ];
        }

        return $data;
    }
}