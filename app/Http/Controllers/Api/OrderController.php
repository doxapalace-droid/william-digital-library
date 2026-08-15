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
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest('created_at')
            ->paginate(10);

        return response()->json([
            'data' => $orders->getCollection()
                ->map(fn (Order $order) => $this->formatOrder($order))
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
     * The summary response does not include order items.
     * The detail response includes the purchased books.
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

            'paid_at' => $order->paid_at,

            'created_at' => $order->created_at,

            'updated_at' => $order->updated_at,
        ];

        if ($includeItems) {
            $data['items'] = $order->items
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'uuid' => $item->uuid,

                        'quantity' => (int) $item->quantity,

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
                ->values();
        }

        return $data;
    }
}