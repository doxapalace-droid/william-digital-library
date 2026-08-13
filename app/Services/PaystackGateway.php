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
            (string) config(
                'services.paystack.base_url',
                'https://api.paystack.co'
            ),
            '/'
        );

        $this->secretKey = (string) config(
            'services.paystack.secret_key'
        );

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

        /**
         * Paystack requires a valid customer email.
         */
        if (! $payment->user?->email) {
            throw new RuntimeException(
                'A valid customer email is required for payment.'
            );
        }

        /**
         * Validate the payment currency before
         * sending anything to Paystack.
         */
        $currency = $this->validateCurrency(
            $payment->currency
        );

        /**
         * Use an existing reference if one exists.
         * Otherwise generate a unique reference.
         */
        $reference = $payment->transaction_reference;

        if (! $reference) {
            $reference = 'DP-' . strtoupper(
                str_replace(
                    '-',
                    '',
                    (string) $payment->uuid
                )
            );
        }

        /**
         * Paystack expects amounts in the smallest
         * currency unit.
         */
        $amount = $this->toSubunit(
            $payment->amount,
            $currency
        );

        $payload = [
            'email' => $payment->user->email,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
        ];

        /**
         * Include callback URL when configured.
         */
        $callbackUrl = config(
            'services.paystack.callback_url'
        );

        if ($callbackUrl) {
            $payload['callback_url'] = $callbackUrl;
        }

        /**
         * Send request to Paystack.
         */
        $response = Http::acceptJson()
            ->withToken($this->secretKey)
            ->timeout(30)
            ->post(
                $this->baseUrl . '/transaction/initialize',
                $payload
            );

        $data = $response->json();

        /**
         * Handle Paystack errors.
         */
        if (
            ! $response->successful()
            || ! ($data['status'] ?? false)
        ) {
            throw new RuntimeException(
                $data['message']
                ?? 'Unable to initialize Paystack payment.'
            );
        }

        return [
            'authorization_url' =>
                $data['data']['authorization_url'] ?? null,

            'access_code' =>
                $data['data']['access_code'] ?? null,

            'reference' =>
                $data['data']['reference'] ?? $reference,

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

        $currency = $this->validateCurrency(
            $payment->currency
        );

        $response = Http::acceptJson()
            ->withToken($this->secretKey)
            ->timeout(30)
            ->get(
                $this->baseUrl
                . '/transaction/verify/'
                . urlencode($reference)
            );

        $data = $response->json();

        /**
         * Handle HTTP-level errors.
         */
        if (! $response->successful()) {
            throw new RuntimeException(
                $data['message']
                ?? 'Unable to verify Paystack payment.'
            );
        }

        $transaction = $data['data'] ?? [];

        return [
            'successful' =>
                ($data['status'] ?? false)
                && ($transaction['status'] ?? null) === 'success',

            'reference' =>
                $transaction['reference'] ?? $reference,

            'amount' =>
                isset($transaction['amount'])
                    ? $this->fromSubunit(
                        $transaction['amount'],
                        $currency
                    )
                    : null,

            'currency' =>
                isset($transaction['currency'])
                    ? strtoupper($transaction['currency'])
                    : null,

            'raw' => $data,
        ];
    }

    /**
     * Validate Paystack-supported currency.
     */
    protected function validateCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        $supportedCurrencies = [
            'GHS',
            'NGN',
            'USD',
            'ZAR',
            'KES',
            'XOF',
        ];

        if (! in_array(
            $currency,
            $supportedCurrencies,
            true
        )) {
            throw new RuntimeException(
                "Unsupported Paystack currency: {$currency}."
            );
        }

        return $currency;
    }

    /**
     * Convert major currency amount to subunit.
     *
     * Example:
     *
     * GHS 20.00 = 2000
     */
    protected function toSubunit(
        string|float|int $amount,
        string $currency
    ): int {
        return (int) round(
            ((float) $amount) * 100
        );
    }

    /**
     * Convert subunit back to major currency.
     *
     * Example:
     *
     * 2000 = GHS 20.00
     */
    protected function fromSubunit(
        string|float|int $amount,
        string $currency
    ): float {
        return ((float) $amount) / 100;
    }
}