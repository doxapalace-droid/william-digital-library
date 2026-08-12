<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;

    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            config('services.paystack.base_url', 'https://api.paystack.co'),
            '/'
        );

        $this->secretKey = (string) config('services.paystack.secret_key');

        if ($this->secretKey === '') {
            throw new RuntimeException(
                'Paystack secret key is not configured.'
            );
        }
    }

    /**
     * Initialize a payment with Paystack.
     */
    public function initialize(Payment $payment): array
    {
        $payment->loadMissing([
            'user',
            'order',
        ]);

        if (! $payment->user?->email) {
            throw new RuntimeException(
                'A valid customer email is required for payment.'
            );
        }

        $reference = $payment->transaction_reference
            ?: 'DP-' . strtoupper(str_replace('-', '', $payment->uuid));

        $amount = $this->toSubunit(
            $payment->amount,
            $payment->currency
        );

        $payload = [
            'email' => $payment->user->email,
            'amount' => $amount,
            'currency' => strtoupper($payment->currency),
            'reference' => $reference,
        ];

        $callbackUrl = config('services.paystack.callback_url');

        if ($callbackUrl) {
            $payload['callback_url'] = $callbackUrl;
        }

        $response = Http::acceptJson()
            ->withToken($this->secretKey)
            ->timeout(30)
            ->post(
                $this->baseUrl . '/transaction/initialize',
                $payload
            );

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new RuntimeException(
                $data['message']
                ?? 'Unable to initialize Paystack payment.'
            );
        }

        return [
            'authorization_url' => $data['data']['authorization_url'] ?? null,
            'access_code' => $data['data']['access_code'] ?? null,
            'reference' => $data['data']['reference'] ?? $reference,
            'raw' => $data,
        ];
    }

    /**
     * Verify a payment with Paystack.
     */
    public function verify(Payment $payment): array
    {
        $reference = $payment->transaction_reference;

        if (! $reference) {
            throw new RuntimeException(
                'Payment does not have a transaction reference.'
            );
        }

        $response = Http::acceptJson()
            ->withToken($this->secretKey)
            ->timeout(30)
            ->get(
                $this->baseUrl . '/transaction/verify/' . urlencode($reference)
            );

        $data = $response->json();

        if (! $response->successful()) {
            throw new RuntimeException(
                $data['message']
                ?? 'Unable to verify Paystack payment.'
            );
        }

        $transaction = $data['data'] ?? [];

        return [
            'successful' => ($data['status'] ?? false)
                && ($transaction['status'] ?? null) === 'success',

            'reference' => $transaction['reference'] ?? $reference,

            /*
             * Paystack returns the amount in the currency subunit.
             * Convert it back to the major currency unit before
             * returning it to PaymentService.
             */
            'amount' => isset($transaction['amount'])
                ? $this->fromSubunit(
                    $transaction['amount'],
                    $payment->currency
                )
                : null,

            'currency' => isset($transaction['currency'])
                ? strtoupper($transaction['currency'])
                : null,

            'raw' => $data,
        ];
    }

    /**
     * Convert a major currency amount to its Paystack subunit.
     *
     * Example:
     * GHS 20.00 -> 2000
     */
    protected function toSubunit(
        string|float|int $amount,
        string $currency
    ): int {
        /*
         * Paystack currently expects supported currency amounts
         * in subunits. For GHS, USD, NGN, ZAR and KES this is
         * normally 100 units to one major unit.
         *
         * XOF is also sent multiplied by 100 according to
         * Paystack's API documentation.
         */
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Convert a Paystack subunit amount back to major currency.
     */
    protected function fromSubunit(
        string|float|int $amount,
        string $currency
    ): float {
        return ((float) $amount) / 100;
    }
}