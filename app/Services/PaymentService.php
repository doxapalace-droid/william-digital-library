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
        /**
         * Make sure the order belongs to the user.
         */
        if ((int) $order->user_id !== (int) $user->id) {
            throw new RuntimeException(
                'You cannot pay for another user\'s order.'
            );
        }

        /**
         * Only payable orders can be submitted.
         */
        if (! $order->canBePaid()) {
            throw new RuntimeException(
                'This order cannot be paid.'
            );
        }

        /**
         * Prevent duplicate pending or successful
         * payments for the same order.
         */
        $existingPayment = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [
                'pending',
                'successful',
            ])
            ->first();

        if ($existingPayment) {
            return $existingPayment;
        }

        /**
         * Create payment from the order snapshot.
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
                'status' => 'pending',
                'currency' => strtoupper($order->currency),
                'amount' => $order->total,
            ]);
        });

        /**
         * Send payment to gateway.
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
                    $this->encodeGatewayResponse(
                        $response['raw'] ?? $response
                    ),
            ]);

            return $payment->fresh();
        } catch (\Throwable $exception) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' =>
                    $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Verify a payment with the configured gateway.
     */
    public function verify(Payment $payment): Payment
    {
        /**
         * Successful payments do not need
         * to be verified again.
         */
        if ($payment->isSuccessful()) {
            return $payment;
        }

        $result = $this->gateway->verify($payment);

        /**
         * Gateway says payment failed.
         */
        if (! ($result['successful'] ?? false)) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' =>
                    $this->encodeGatewayResponse(
                        $result['raw'] ?? $result
                    ),
            ]);

            return $payment->fresh();
        }

        /**
         * Verify amount.
         */
        if (
            isset($result['amount'])
            && (float) $result['amount']
                !== (float) $payment->amount
        ) {
            throw new RuntimeException(
                'Payment amount does not match the order amount.'
            );
        }

        /**
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
                    $this->encodeGatewayResponse(
                        $result['raw'] ?? $result
                    ),

                'paid_at' => now(),
            ]);

            /**
             * Only mark the order paid after
             * successful gateway verification.
             */
            $payment->order()->update([
                'payment_status' => 'paid',
                'status' => 'completed',
                'paid_at' => now(),
            ]);
        });

        return $payment->fresh();
    }

    /**
     * Encode gateway response safely.
     */
    protected function encodeGatewayResponse(
        mixed $response
    ): ?string {
        if ($response === null) {
            return null;
        }

        if (is_string($response)) {
            return $response;
        }

        return json_encode(
            $response,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
        );
    }
}