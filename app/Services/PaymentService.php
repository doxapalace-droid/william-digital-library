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
            throw new RuntimeException('You cannot pay for another user\'s order.');
        }

        /*
         * Only payable orders can be submitted for payment.
         */
        if (! $order->canBePaid()) {
            throw new RuntimeException('This order cannot be paid.');
        }

        /*
         * Prevent duplicate pending/successful payments
         * for the same order.
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

        /*
         * Create the payment record from the order snapshot.
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
                'currency' => $order->currency,
                'amount' => $order->total,
            ]);
        });

        /*
         * Send the payment to the selected gateway.
         */
        try {
            $response = $this->gateway->initialize($payment);

            /*
             * Store the gateway reference when supplied.
             */
            if (! empty($response['reference'])) {
                $payment->update([
                    'transaction_reference' => $response['reference'],
                    'gateway_response' => $this->encodeGatewayResponse(
                        $response['raw'] ?? $response
                    ),
                ]);
            } else {
                $payment->update([
                    'gateway_response' => $this->encodeGatewayResponse(
                        $response['raw'] ?? $response
                    ),
                ]);
            }

            return $payment->fresh();
        } catch (\Throwable $exception) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' => $exception->getMessage(),
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
         * A successful payment does not need to be verified again.
         */
        if ($payment->isSuccessful()) {
            return $payment;
        }

        $result = $this->gateway->verify($payment);

        /*
         * Payment failed at the gateway.
         */
        if (! ($result['successful'] ?? false)) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' => $this->encodeGatewayResponse(
                    $result['raw'] ?? $result
                ),
            ]);

            return $payment->fresh();
        }

        /*
         * Verify the gateway amount against the amount
         * recorded in our database.
         */
        if (
            isset($result['amount'])
            && (float) $result['amount'] !== (float) $payment->amount
        ) {
            throw new RuntimeException(
                'Payment amount does not match the order amount.'
            );
        }

        /*
         * Verify currency when the gateway provides it.
         */
        if (
            ! empty($result['currency'])
            && strtoupper($result['currency']) !== strtoupper($payment->currency)
        ) {
            throw new RuntimeException(
                'Payment currency does not match the order currency.'
            );
        }

        DB::transaction(function () use ($payment, $result) {
            $payment->update([
                'status' => 'successful',
                'transaction_reference' =>
                    $result['reference']
                    ?? $payment->transaction_reference,
                'gateway_response' => $this->encodeGatewayResponse(
                    $result['raw'] ?? $result
                ),
                'paid_at' => now(),
            ]);

            /*
             * Mark the order as paid and completed only after
             * successful verification.
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
     * Encode gateway response data safely.
     */
    protected function encodeGatewayResponse(mixed $response): ?string
    {
        if ($response === null) {
            return null;
        }

        if (is_string($response)) {
            return $response;
        }

        return json_encode(
            $response,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}