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
     * The order may contain:
     *
     * - books
     * - audiobooks
     * - both books and audiobooks
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
     * Supports both books and audiobooks.
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
        ];

        /*
         * Book order item.
         */
        if ($item->isBook()) {
            $data['book'] = $item->book
                ? [
                    'id' => $item->book->id,
                    'uuid' => $item->book->uuid,
                    'title' => $item->book->title,
                    'slug' => $item->book->slug,
                    'subtitle' => $item->book->subtitle,
                    'author' => $item->book->author,
                    'cover_image' => $item->book->cover_image,
                ]
                : null;

            $data['audiobook'] = null;
        }

        /*
         * Audiobook order item.
         */
        elseif ($item->isAudiobook()) {
            $data['book'] = null;

            $data['audiobook'] = $item->audiobook
                ? [
                    'id' => $item->audiobook->id,
                    'uuid' => $item->audiobook->uuid,

                    'book_id' => $item->audiobook->book_id,

                    'description' =>
                        $item->audiobook->description,

                    'cover_image' =>
                        $item->audiobook->cover_image
                        ?? $item->audiobook->book?->cover_image,

                    'price' =>
                        $this->formatMoney(
                            $item->audiobook->price
                        ),

                    'currency' =>
                        $item->audiobook->currency,

                    'duration_seconds' =>
                        $item->audiobook->duration_seconds,

                    'duration_minutes' =>
                        $item->audiobook->durationInMinutes(),

                    'status' =>
                        $item->audiobook->status,

                    'published_at' =>
                        $item->audiobook->published_at,
                ]
                : null;
        }

        /*
         * Unknown item type.
         *
         * This should normally never happen because
         * item_type is controlled by checkout validation.
         */
        else {
            $data['book'] = null;
            $data['audiobook'] = null;
        }

        return $data;
    }

    /**
     * Format monetary values consistently.
     *
     * Example:
     *
     * 25      -> "25.00"
     * 25.5    -> "25.50"
     * 25.75   -> "25.75"
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