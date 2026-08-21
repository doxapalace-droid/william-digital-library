<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display the authenticated customer's order history.
     *
     * Only orders belonging to the authenticated customer
     * are returned.
     *
     * Order items are intentionally not loaded here because
     * the history endpoint should remain lightweight.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max(
                (int) $request->input('per_page', 10),
                1
            ),
            50
        );

        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $orders->getCollection()
                ->map(
                    fn (Order $order) => $this->formatOrder($order)
                )
                ->values(),

            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Display one order belonging to the authenticated customer.
     *
     * Supports:
     *
     * - books
     * - audiobooks
     * - courses
     * - bundles
     * - mixed orders
     *
     * Bundle contents are also returned so the customer can
     * see which products are included in the purchased bundle.
     */
    public function show(
        Request $request,
        string $uuid
    ): JsonResponse {
        $order = Order::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with([
                'items' => function ($query) {
                    $query
                        ->with([
                            'book:id,uuid,title,slug,subtitle,author,cover_image',

                            'audiobook:id,uuid,book_id,description,cover_image,price,currency,duration_seconds,status,published_at',

                            'audiobook.book:id,uuid,title,slug,cover_image',

                            'course:id,uuid,title,slug,subtitle,description,cover_image,price,currency,status,published_at',

                            'bundle:id,uuid,name,slug,description,cover_image,price,currency,is_active,is_published,published_at',

                            'bundle.items' => function ($query) {
                                $query->with([
                                    'book:id,uuid,title,slug,subtitle,author,cover_image',
                                    'audiobook:id,uuid,book_id,description,cover_image,price,currency,duration_seconds,status,published_at',
                                    'audiobook.book:id,uuid,title,slug,cover_image',
                                    'course:id,uuid,title,slug,subtitle,description,cover_image,price,currency,status,published_at',
                                    'video:id,uuid,title,slug,description,cover_image,price,currency,status,duration_seconds,published_at',
                                ])->orderBy('id');
                            },
                        ])
                        ->orderBy('id');
                },
            ])
            ->firstOrFail();

        return response()->json([
            'data' => $this->formatOrder(
                $order,
                true
            ),
        ]);
    }

    /**
     * Format an order for the customer-facing API.
     *
     * Summary:
     *     Does not include order items.
     *
     * Detail:
     *     Includes all purchased products.
     */
    private function formatOrder(
        Order $order,
        bool $includeItems = false
    ): array {
        $data = [
            'id' => $order->id,

            'uuid' => $order->uuid,

            'order_number' => $order->order_number,

            'status' => $order->status,

            'payment_status' => $order->payment_status,

            'currency' => $order->currency,

            'subtotal' => $this->formatMoney(
                $order->subtotal
            ),

            'discount' => $this->formatMoney(
                $order->discount
            ),

            'total' => $this->formatMoney(
                $order->total
            ),

            'paid_at' => $order->paid_at,

            'created_at' => $order->created_at,

            'updated_at' => $order->updated_at,

            'items_count' => $order->items_count
                ?? $order->items->count(),
        ];

        if ($includeItems) {
            $data['items'] = $order->items
                ->map(
                    fn ($item) => $this->formatOrderItem($item)
                )
                ->values();
        }

        return $data;
    }

    /**
     * Format an individual order item.
     *
     * Supports:
     *
     * - books
     * - audiobooks
     * - courses
     * - bundles
     */
    private function formatOrderItem($item): array
    {
        $data = [
            'id' => $item->id,

            'uuid' => $item->uuid,

            'item_type' => $item->item_type,

            'quantity' => (int) $item->quantity,

            'unit_price' => $this->formatMoney(
                $item->unit_price
            ),

            'currency' => $item->currency,

            'subtotal' => $this->formatMoney(
                $item->subtotal
            ),

            'book' => null,

            'audiobook' => null,

            'course' => null,

            'bundle' => null,
        ];

        /*
         * Book order item.
         */
        if ($item->isBook()) {
            $data['book'] = $this->formatBook(
                $item->book
            );
        }

        /*
         * Audiobook order item.
         */
        elseif ($item->isAudiobook()) {
            $data['audiobook'] = $this->formatAudiobook(
                $item->audiobook
            );
        }

        /*
         * Course order item.
         */
        elseif ($item->isCourse()) {
            $data['course'] = $this->formatCourse(
                $item->course
            );
        }

        /*
         * Bundle order item.
         */
        elseif ($item->isBundle()) {
            $data['bundle'] = $this->formatBundle(
                $item->bundle
            );
        }

        return $data;
    }

    /**
     * Format a book for the customer-facing API.
     */
    private function formatBook($book): ?array
    {
        if (! $book) {
            return null;
        }

        return [
            'id' => $book->id,

            'uuid' => $book->uuid,

            'title' => $book->title,

            'slug' => $book->slug,

            'subtitle' => $book->subtitle,

            'author' => $book->author,

            'cover_image' => $book->cover_image,
        ];
    }

    /**
     * Format an audiobook for the customer-facing API.
     */
    private function formatAudiobook($audiobook): ?array
    {
        if (! $audiobook) {
            return null;
        }

        return [
            'id' => $audiobook->id,

            'uuid' => $audiobook->uuid,

            'book_id' => $audiobook->book_id,

            'title' => $audiobook->book?->title,

            'slug' => $audiobook->book?->slug,

            'description' => $audiobook->description,

            'cover_image' =>
                $audiobook->cover_image
                ?? $audiobook->book?->cover_image,

            'price' => $this->formatMoney(
                $audiobook->price
            ),

            'currency' => $audiobook->currency,

            'duration_seconds' =>
                (int) $audiobook->duration_seconds,

            'duration_minutes' =>
                $audiobook->durationInMinutes(),

            'status' => $audiobook->status,

            'published_at' => $audiobook->published_at,
        ];
    }

    /**
     * Format a course for the customer-facing API.
     */
    private function formatCourse($course): ?array
    {
        if (! $course) {
            return null;
        }

        return [
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

            'currency' => $course->currency,

            'status' => $course->status,

            'published_at' => $course->published_at,
        ];
    }

    /**
     * Format a bundle for the customer-facing API.
     *
     * The bundle itself is returned together with its
     * contained products.
     */
    private function formatBundle($bundle): ?array
    {
        if (! $bundle) {
            return null;
        }

        return [
            'id' => $bundle->id,

            'uuid' => $bundle->uuid,

            'name' => $bundle->name,

            'slug' => $bundle->slug,

            'description' => $bundle->description,

            'cover_image' => $bundle->cover_image,

            'price' => $this->formatMoney(
                $bundle->price
            ),

            'currency' => $bundle->currency,

            'is_active' => (bool) $bundle->is_active,

            'is_published' => (bool) $bundle->is_published,

            'published_at' => $bundle->published_at,

            'items' => $bundle->items
                ->map(
                    fn ($item) => $this->formatBundleItem($item)
                )
                ->values(),
        ];
    }

    /**
     * Format a product contained inside a bundle.
     */
    private function formatBundleItem($item): array
    {
        $data = [
            'id' => $item->id,

            'uuid' => $item->uuid,

            'item_type' => $item->item_type,

            'book' => null,

            'audiobook' => null,

            'course' => null,

            'video' => null,
        ];

        if ($item->isBook()) {
            $data['book'] = $this->formatBook(
                $item->book
            );
        }

        elseif ($item->isAudiobook()) {
            $data['audiobook'] = $this->formatAudiobook(
                $item->audiobook
            );
        }

        elseif ($item->isCourse()) {
            $data['course'] = $this->formatCourse(
                $item->course
            );
        }

        elseif ($item->isVideo()) {
            $data['video'] = $this->formatVideo(
                $item->video
            );
        }

        return $data;
    }

    /**
     * Format a video contained inside a bundle.
     *
     * Videos are currently catalogue data only.
     */
    private function formatVideo($video): ?array
    {
        if (! $video) {
            return null;
        }

        return [
            'id' => $video->id,

            'uuid' => $video->uuid,

            'title' => $video->title,

            'slug' => $video->slug,

            'description' => $video->description,

            'cover_image' => $video->cover_image,

            'price' => $this->formatMoney(
                $video->price
            ),

            'currency' => $video->currency,

            'status' => $video->status,

            'duration_seconds' =>
                (int) $video->duration_seconds,

            'published_at' => $video->published_at,
        ];
    }

    /**
     * Format monetary values consistently.
     */
    private function formatMoney($value): string
    {
        return number_format(
            (float) $value,
            2,
            '.',
            ''
        );
    }
}