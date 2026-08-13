<?php

namespace App\Services;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Initialize a payment with the external gateway.
     *
     * @return array{
     *     authorization_url: string|null,
     *     access_code: string|null,
     *     reference: string|null,
     *     raw: mixed
     * }
     */
    public function initialize(Payment $payment): array;

    /**
     * Verify a payment with the external gateway.
     *
     * @return array{
     *     successful: bool,
     *     reference: string|null,
     *     amount: string|float|int|null,
     *     currency: string|null,
     *     raw: mixed
     * }
     */
    public function verify(Payment $payment): array;
}