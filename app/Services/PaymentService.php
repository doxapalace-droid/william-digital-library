<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {
    }

    /**
     * Initialize a payment for an order.
     */
    public function initialize(
        User $user,
        Order $order,
        string $gatewayName = 'paystack'
    ): Payment {
        /*
         * Make sure the order belongs to the authenticated user.
         */
        if ((int) $order->user_id !== (int) $user->id) {
            throw new RuntimeException(
                'You cannot pay for another user\'s order.'
            );
        }

        /*
         * Only payable orders can be submitted.
         */
        if (! $order->canBePaid()) {
            throw new RuntimeException(
                'This order cannot be paid.'
            );
        }

        /*
         * Prevent duplicate pending or successful
         * payments for the same order.
         */
        $existingPayment = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [
                'pending',
                'successful',
            ])
            ->latest('id')
            ->first();

        if ($existingPayment) {
            return $existingPayment;
        }

        /*
         * Create the local payment record first.
         */
        $payment = DB::transaction(function () use (
            $user,
            $order,
            $gatewayName
        ) {
            return Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'gateway' => $gatewayName,
                'transaction_reference' => null,
                'status' => 'pending',
                'currency' => strtoupper($order->currency),
                'amount' => $order->total,
                'gateway_response' => null,
                'paid_at' => null,
                'failed_at' => null,
            ]);
        });

        /*
         * Initialize the payment with the gateway.
         */
        try {
            $response = $this->gateway->initialize(
                $payment
            );

            $payment->update([
                'transaction_reference' =>
                    $response['reference']
                    ?? $payment->transaction_reference,

                'gateway_response' =>
                    $this->normalizeGatewayResponse(
                        $response['raw'] ?? $response
                    ),
            ]);

            return $payment->fresh();

        } catch (\Throwable $exception) {

            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' => [
                    'error' => $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }
    }

    /**
     * Verify a payment with the configured gateway.
     */
    public function verify(Payment $payment): Payment
    {
        /*
         * Successful payments do not need
         * to be verified again.
         */
        if ($payment->isSuccessful()) {
            return $payment;
        }

        $result = $this->gateway->verify(
            $payment
        );

        /*
         * Gateway says payment failed.
         */
        if (! ($result['successful'] ?? false)) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' =>
                    $this->normalizeGatewayResponse(
                        $result['raw'] ?? $result
                    ),
            ]);

            return $payment->fresh();
        }

        /*
         * Verify amount.
         */
        if (isset($result['amount'])) {
            $expectedAmount = round(
                (float) $payment->amount,
                2
            );

            $receivedAmount = round(
                (float) $result['amount'],
                2
            );

            if (
                abs(
                    $expectedAmount - $receivedAmount
                ) > 0.01
            ) {
                throw new RuntimeException(
                    'Payment amount does not match the order amount.'
                );
            }
        }

        /*
         * Verify currency.
         */
        if (
            ! empty($result['currency'])
            && strtoupper($result['currency'])
                !== strtoupper($payment->currency)
        ) {
            throw new RuntimeException(
                'Payment currency does not match the order currency.'
            );
        }

        /*
         * Mark payment and order as successful atomically.
         */
        DB::transaction(function () use (
            $payment,
            $result
        ) {
            $payment->update([
                'status' => 'successful',

                'transaction_reference' =>
                    $result['reference']
                    ?? $payment->transaction_reference,

                'gateway_response' =>
                    $this->normalizeGatewayResponse(
                        $result['raw'] ?? $result
                    ),

                'paid_at' => now(),

                'failed_at' => null,
            ]);

            $payment->order()->update([
                'payment_status' => 'paid',
                'status' => 'completed',
                'paid_at' => now(),
            ]);
        });

        return $payment->fresh();
    }

    /**
     * Normalize gateway response data.
     *
     * Payment.gateway_response is cast to an array
     * by the Payment model, so always return an array.
     */
    protected function normalizeGatewayResponse(
        mixed $response
    ): ?array {
        if ($response === null) {
            return null;
        }

        if (is_array($response)) {
            return $response;
        }

        if (is_object($response)) {
            return json_decode(
                json_encode($response),
                true
            );
        }

        if (is_string($response)) {
            return [
                'message' => $response,
            ];
        }

        return [
            'response' => $response,
        ];
    }
}