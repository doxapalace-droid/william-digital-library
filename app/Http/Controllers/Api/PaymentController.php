<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Display the authenticated user's payment history for an order.
     */
    public function index(
        Request $request,
        string $uuid
    ): JsonResponse {
        $order = Order::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with('payments')
            ->firstOrFail();

        return response()->json([
            'data' => $order->payments
                ->map(fn (Payment $payment) => $this->formatPayment($payment))
                ->values(),
        ]);
    }

    /**
     * Initiate payment for an unpaid order.
     *
     * This creates the internal payment record.
     * Actual gateway processing will be added separately.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_uuid' => [
                'required',
                'uuid',
            ],
            'gateway' => [
                'required',
                'string',
                'max:50',
            ],
        ]);

        $user = $request->user();

        $order = Order::query()
            ->where('uuid', $validated['order_uuid'])
            ->where('user_id', $user->id)
            ->with('items')
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'order_uuid' => [
                    'The selected order could not be found.',
                ],
            ]);
        }

        if (! $order->canBePaid()) {
            throw ValidationException::withMessages([
                'order_uuid' => [
                    'This order cannot be paid.',
                ],
            ]);
        }

        if ((float) $order->total <= 0) {
            throw ValidationException::withMessages([
                'order_uuid' => [
                    'This order has no payable amount.',
                ],
            ]);
        }

        /*
         * Do not create another payment when a pending
         * payment already exists for this order and gateway.
         */
        $existingPayment = Payment::query()
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->where('gateway', $validated['gateway'])
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existingPayment) {
            return response()->json([
                'data' => $this->formatPayment($existingPayment),
                'message' => 'A pending payment already exists for this order.',
            ]);
        }

        /*
         * A successful payment means the order has already
         * been paid. Do not create another payment.
         */
        $successfulPaymentExists = Payment::query()
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->where('status', 'successful')
            ->exists();

        if ($successfulPaymentExists || $order->isPaid()) {
            throw ValidationException::withMessages([
                'order_uuid' => [
                    'This order has already been paid.',
                ],
            ]);
        }

        $payment = DB::transaction(function () use (
            $order,
            $user,
            $validated
        ) {
            return Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'gateway' => $validated['gateway'],
                'transaction_reference' => null,
                'status' => 'pending',
                'currency' => $order->currency,
                'amount' => $order->total,
                'gateway_response' => null,
                'paid_at' => null,
                'failed_at' => null,
            ]);
        });

        return response()->json([
            'data' => $this->formatPayment($payment),
            'message' => 'Payment initialized successfully.',
        ], 201);
    }

    /**
     * Display a single payment.
     */
    public function show(
        Request $request,
        string $uuid
    ): JsonResponse {
        $payment = Payment::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'data' => $this->formatPayment($payment),
        ]);
    }

    /**
     * Verify a payment.
     *
     * Actual gateway verification will be implemented
     * when the payment gateway service is connected.
     */
    public function verify(
        Request $request,
        string $uuid
    ): JsonResponse {
        $payment = Payment::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($payment->isSuccessful()) {
            return response()->json([
                'data' => $this->formatPayment($payment),
                'message' => 'Payment has already been verified successfully.',
            ]);
        }

        return response()->json([
            'message' => 'Payment gateway verification has not been configured yet.',
            'data' => $this->formatPayment($payment),
        ], 501);
    }

    /**
     * Format a payment for the customer-facing API.
     */
    private function formatPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'uuid' => $payment->uuid,
            'order_id' => $payment->order_id,
            'gateway' => $payment->gateway,
            'transaction_reference' => $payment->transaction_reference,
            'status' => $payment->status,
            'currency' => $payment->currency,
            'amount' => $payment->amount,
            'paid_at' => $payment->paid_at,
            'failed_at' => $payment->failed_at,
            'created_at' => $payment->created_at,
        ];
    }
}