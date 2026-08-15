<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminOrderController extends Controller
{
    /**
     * Display all orders for administrators.
     *
     * Supports:
     * - Search by order number
     * - Filter by order status
     * - Filter by payment status
     * - Pagination
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'pending',
                    'processing',
                    'completed',
                    'cancelled',
                    'failed',
                ]),
            ],

            'payment_status' => [
                'nullable',
                'string',
                Rule::in([
                    'unpaid',
                    'pending',
                    'paid',
                    'failed',
                    'refunded',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Order::query()
            ->with([
                'user:id,name,email',
            ])
            ->orderByDesc('created_at');

        /*
         * Search by order number.
         */
        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(
                'order_number',
                'like',
                '%' . $search . '%'
            );
        }

        /*
         * Filter by order status.
         */
        if (! empty($validated['status'])) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        /*
         * Filter by payment status.
         */
        if (! empty($validated['payment_status'])) {
            $query->where(
                'payment_status',
                $validated['payment_status']
            );
        }

        $orders = $query->paginate(
            $validated['per_page'] ?? 20
        );

        return response()->json([
            'data' => $orders->getCollection()
                ->map(
                    fn (Order $order) =>
                        $this->formatOrderSummary($order)
                )
                ->values(),

            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Display a single order for administrators.
     *
     * Includes:
     * - Customer
     * - Order items
     * - Books
     * - Payments
     */
    public function show(string $uuid): JsonResponse
    {
        $order = Order::query()
            ->where('uuid', $uuid)
            ->with([
                'user:id,name,email',
                'items.book:id,uuid,title,slug,subtitle,author,cover_image',
                'payments',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => $this->formatOrderDetails($order),
        ]);
    }

    /**
     * Update the status of an order.
     *
     * This endpoint is intentionally limited to
     * administrative order lifecycle changes.
     */
    public function update(
        Request $request,
        string $uuid
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'pending',
                    'processing',
                    'completed',
                    'cancelled',
                    'failed',
                ]),
            ],

            'payment_status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'unpaid',
                    'pending',
                    'paid',
                    'failed',
                    'refunded',
                ]),
            ],
        ]);

        $order = Order::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        /*
         * If the payment status is changed to paid,
         * record the payment completion time.
         */
        if (
            isset($validated['payment_status'])
            && $validated['payment_status'] === 'paid'
            && $order->payment_status !== 'paid'
        ) {
            $validated['paid_at'] = now();
        }

        /*
         * If the payment status is changed away from paid,
         * remove the paid timestamp.
         */
        if (
            isset($validated['payment_status'])
            && $validated['payment_status'] !== 'paid'
        ) {
            $validated['paid_at'] = null;
        }

        $order->update($validated);

        return response()->json([
            'message' => 'Order updated successfully.',
            'data' => $order->fresh([
                'user:id,name,email',
                'items.book:id,uuid,title,slug,subtitle,author,cover_image',
                'payments',
            ]),
        ]);
    }

    /**
     * Format an order for the admin order list.
     */
    private function formatOrderSummary(Order $order): array
    {
        return [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,

            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
            ],

            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'currency' => $order->currency,

            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'total' => $order->total,

            'paid_at' => $order->paid_at,
            'created_at' => $order->created_at,
        ];
    }

    /**
     * Format an order with its complete details.
     */
    private function formatOrderDetails(Order $order): array
    {
        return [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,

            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
            ],

            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'currency' => $order->currency,

            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'total' => $order->total,

            'paid_at' => $order->paid_at,

            'items' => $order->items
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'uuid' => $item->uuid,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'currency' => $item->currency,
                        'subtotal' => $item->subtotal,

                        'book' => [
                            'id' => $item->book?->id,
                            'uuid' => $item->book?->uuid,
                            'title' => $item->book?->title,
                            'slug' => $item->book?->slug,
                            'subtitle' => $item->book?->subtitle,
                            'author' => $item->book?->author,
                            'cover_image' => $item->book?->cover_image,
                        ],
                    ];
                })
                ->values(),

            'payments' => $order->payments
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'uuid' => $payment->uuid,
                        'gateway' => $payment->gateway,
                        'transaction_reference' =>
                            $payment->transaction_reference,
                        'status' => $payment->status,
                        'currency' => $payment->currency,
                        'amount' => $payment->amount,
                        'paid_at' => $payment->paid_at,
                        'failed_at' => $payment->failed_at,
                        'created_at' => $payment->created_at,
                    ];
                })
                ->values(),

            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }
}