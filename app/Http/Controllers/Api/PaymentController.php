<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudiobookEntitlement;
use App\Models\BookEntitlement;
use App\Models\CourseEntitlement;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {
    }

    /**
     * Display the authenticated user's payment history
     * for an order.
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
                ->map(
                    fn (Payment $payment) =>
                        $this->formatPayment($payment)
                )
                ->values(),
        ]);
    }

    /**
     * Initialize a payment for an unpaid order.
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
         * If a pending payment already exists for this
         * order and gateway, reuse it.
         */
        $existingPayment = Payment::query()
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->where('gateway', $validated['gateway'])
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existingPayment) {
            /*
             * Already initialized.
             */
            if ($existingPayment->transaction_reference) {
                return response()->json([
                    'data' => $this->formatPayment(
                        $existingPayment
                    ),

                    'message' =>
                        'A pending payment already exists for this order.',
                ]);
            }

            /*
             * Pending record exists but has not yet
             * received a gateway reference.
             */
            try {
                $gatewayResponse = $this->gateway->initialize(
                    $existingPayment
                );
            } catch (RuntimeException $exception) {
                $existingPayment->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'gateway_response' => [
                        'error' => $exception->getMessage(),
                    ],
                ]);

                throw ValidationException::withMessages([
                    'gateway' => [
                        $exception->getMessage(),
                    ],
                ]);
            }

            $existingPayment->update([
                'transaction_reference' =>
                    $gatewayResponse['reference']
                    ?? $existingPayment->transaction_reference,

                'gateway_response' =>
                    $gatewayResponse['raw'] ?? null,
            ]);

            $existingPayment->refresh();

            return response()->json([
                'data' => $this->formatPayment(
                    $existingPayment
                ),

                'payment' => [
                    'authorization_url' =>
                        $gatewayResponse['authorization_url']
                        ?? null,

                    'access_code' =>
                        $gatewayResponse['access_code']
                        ?? null,

                    'reference' =>
                        $gatewayResponse['reference']
                        ?? $existingPayment->transaction_reference,
                ],

                'message' =>
                    'Payment initialized successfully.',
            ]);
        }

        /*
         * Never create another payment if the order
         * has already been successfully paid.
         */
        $successfulPaymentExists = Payment::query()
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->where('status', 'successful')
            ->exists();

        if (
            $successfulPaymentExists
            || $order->isPaid()
        ) {
            throw ValidationException::withMessages([
                'order_uuid' => [
                    'This order has already been paid.',
                ],
            ]);
        }

        /*
         * Create the local payment record first.
         */
        $payment = DB::transaction(
            function () use (
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
                    'currency' => strtoupper(
                        $order->currency
                    ),
                    'amount' => $order->total,
                    'gateway_response' => null,
                    'paid_at' => null,
                    'failed_at' => null,
                ]);
            }
        );

        /*
         * Initialize the transaction with the
         * actual payment gateway.
         */
        try {
            $gatewayResponse = $this->gateway->initialize(
                $payment
            );
        } catch (RuntimeException $exception) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' => [
                    'error' => $exception->getMessage(),
                ],
            ]);

            throw ValidationException::withMessages([
                'gateway' => [
                    $exception->getMessage(),
                ],
            ]);
        }

        /*
         * Store gateway reference and response.
         */
        $payment->update([
            'transaction_reference' =>
                $gatewayResponse['reference'] ?? null,

            'gateway_response' =>
                $gatewayResponse['raw'] ?? null,
        ]);

        $payment->refresh();

        return response()->json([
            'data' => $this->formatPayment(
                $payment
            ),

            'payment' => [
                'authorization_url' =>
                    $gatewayResponse['authorization_url']
                    ?? null,

                'access_code' =>
                    $gatewayResponse['access_code']
                    ?? null,

                'reference' =>
                    $gatewayResponse['reference']
                    ?? $payment->transaction_reference,
            ],

            'message' =>
                'Payment initialized successfully.',
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
            'data' => $this->formatPayment(
                $payment
            ),
        ]);
    }

    /**
     * Verify a payment with the configured gateway.
     */
    public function verify(
        Request $request,
        string $uuid
    ): JsonResponse {
        $payment = Payment::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with([
                'order.items.book',
                'order.items.audiobook',
                'order.items.course',

                /*
                 * Bundle contents must be loaded so that
                 * successful bundle purchases can grant
                 * the appropriate entitlements.
                 */
                'order.items.bundle.items.book',
                'order.items.bundle.items.audiobook',
                'order.items.bundle.items.course',
                'order.items.bundle.items.video',
            ])
            ->firstOrFail();

        /*
         * Do not process a successful payment twice.
         */
        if ($payment->isSuccessful()) {
            return response()->json([
                'data' => $this->formatPayment(
                    $payment
                ),

                'message' =>
                    'Payment has already been verified successfully.',
            ]);
        }

        try {
            $result = $this->gateway->verify(
                $payment
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),

                'data' =>
                    $this->formatPayment(
                        $payment
                    ),
            ], 422);
        }

        /*
         * Verify the gateway reference.
         */
        if (
            ! empty($result['reference'])
            && $payment->transaction_reference
            && $result['reference']
                !== $payment->transaction_reference
        ) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' =>
                    $result['raw'] ?? null,
            ]);

            return response()->json([
                'message' =>
                    'Payment reference verification failed.',

                'data' =>
                    $this->formatPayment(
                        $payment
                    ),
            ], 422);
        }

        /*
         * Verify currency.
         */
        if (
            ! empty($result['currency'])
            && strtoupper($result['currency'])
                !== strtoupper($payment->currency)
        ) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' =>
                    $result['raw'] ?? null,
            ]);

            return response()->json([
                'message' =>
                    'Payment currency verification failed.',

                'data' =>
                    $this->formatPayment(
                        $payment
                    ),
            ], 422);
        }

        /*
         * Verify payment amount.
         */
        if ($result['amount'] !== null) {
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
                $payment->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'gateway_response' =>
                        $result['raw'] ?? null,
                ]);

                return response()->json([
                    'message' =>
                        'Payment amount verification failed.',

                    'data' =>
                        $this->formatPayment(
                            $payment
                        ),
                ], 422);
            }
        }

        /*
         * Payment was not successful.
         */
        if (! ($result['successful'] ?? false)) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'gateway_response' =>
                    $result['raw'] ?? null,
            ]);

            return response()->json([
                'message' =>
                    'Payment was not successful.',

                'data' =>
                    $this->formatPayment(
                        $payment->fresh()
                    ),
            ], 422);
        }

        /*
         * The gateway confirms successful payment.
         *
         * Atomically:
         *
         * 1. Mark payment successful.
         * 2. Mark order paid.
         * 3. Complete order.
         * 4. Grant direct-product entitlements.
         * 5. Expand bundle entitlements.
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
                    $result['raw'] ?? null,

                'paid_at' => now(),

                'failed_at' => null,
            ]);

            $order = $payment->order;

            $order->update([
                'payment_status' => 'paid',
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            /*
             * Grant the correct entitlement for every
             * purchased digital product.
             */
            foreach ($order->items as $item) {

                /*
                 * DIRECT BOOK PURCHASE
                 */
                if (
                    $item->isBook()
                    && $item->book_id
                ) {
                    $this->grantBookEntitlement(
                        $order->user_id,
                        $item->book_id
                    );

                    continue;
                }

                /*
                 * DIRECT AUDIOBOOK PURCHASE
                 */
                if (
                    $item->isAudiobook()
                    && $item->audiobook_id
                ) {
                    $this->grantAudiobookEntitlement(
                        $order->user_id,
                        $item->audiobook_id
                    );

                    continue;
                }

                /*
                 * DIRECT COURSE PURCHASE
                 */
                if (
                    $item->isCourse()
                    && $item->course_id
                ) {
                    $this->grantCourseEntitlement(
                        $order->user_id,
                        $item->course_id
                    );

                    continue;
                }

                /*
                 * BUNDLE PURCHASE
                 *
                 * A bundle is a commercial container.
                 * Its individual digital products are
                 * converted into the corresponding
                 * customer entitlements.
                 */
                if (
                    $item->isBundle()
                    && $item->bundle
                ) {
                    $this->grantBundleEntitlements(
                        $order->user_id,
                        $item->bundle
                    );
                }
            }
        });

        $payment->refresh();

        $payment->load([
            'order',
        ]);

        return response()->json([
            'data' => $this->formatPayment(
                $payment
            ),

            'order' => [
                'uuid' =>
                    $payment->order->uuid,

                'status' =>
                    $payment->order->status,

                'payment_status' =>
                    $payment->order->payment_status,

                'paid_at' =>
                    $payment->order->paid_at,
            ],

            'message' =>
                'Payment verified successfully.',
        ]);
    }

    /**
     * Grant all applicable entitlements contained
     * in a purchased bundle.
     *
     * Videos are intentionally ignored because
     * the current application does not have a
     * VideoEntitlement model/system.
     */
    private function grantBundleEntitlements(
        int $userId,
        $bundle
    ): void {
        foreach ($bundle->items as $bundleItem) {

            /*
             * Bundle book.
             */
            if (
                $bundleItem->isBook()
                && $bundleItem->book_id
            ) {
                $this->grantBookEntitlement(
                    $userId,
                    $bundleItem->book_id
                );

                continue;
            }

            /*
             * Bundle audiobook.
             */
            if (
                $bundleItem->isAudiobook()
                && $bundleItem->audiobook_id
            ) {
                $this->grantAudiobookEntitlement(
                    $userId,
                    $bundleItem->audiobook_id
                );

                continue;
            }

            /*
             * Bundle course.
             */
            if (
                $bundleItem->isCourse()
                && $bundleItem->course_id
            ) {
                $this->grantCourseEntitlement(
                    $userId,
                    $bundleItem->course_id
                );

                continue;
            }

            /*
             * Bundle videos currently require no
             * entitlement because videos are currently
             * publicly accessible once active/published.
             */
            if (
                $bundleItem->isVideo()
                && $bundleItem->video_id
            ) {
                continue;
            }
        }
    }

    /**
     * Grant a book entitlement.
     */
    private function grantBookEntitlement(
        int $userId,
        int $bookId
    ): void {
        BookEntitlement::firstOrCreate(
            [
                'user_id' => $userId,
                'book_id' => $bookId,
            ],
            [
                'source' => 'purchase',
                'can_read' => true,
                'can_download' => true,
                'status' => 'active',
                'granted_at' => now(),
                'expires_at' => null,
                'revoked_at' => null,
            ]
        );
    }

    /**
     * Grant an audiobook entitlement.
     */
    private function grantAudiobookEntitlement(
        int $userId,
        int $audiobookId
    ): void {
        AudiobookEntitlement::firstOrCreate(
            [
                'user_id' => $userId,
                'audiobook_id' => $audiobookId,
            ],
            [
                'source' => 'purchase',
                'can_stream' => true,
                'can_download' => true,
                'status' => 'active',
                'granted_at' => now(),
                'expires_at' => null,
                'revoked_at' => null,
            ]
        );
    }

    /**
     * Grant a course entitlement.
     */
    private function grantCourseEntitlement(
        int $userId,
        int $courseId
    ): void {
        CourseEntitlement::firstOrCreate(
            [
                'user_id' => $userId,
                'course_id' => $courseId,
            ],
            [
                'source' => 'purchase',
                'can_access' => true,
                'status' => 'active',
                'granted_at' => now(),
                'expires_at' => null,
                'revoked_at' => null,
            ]
        );
    }

    /**
     * Format a payment for the customer-facing API.
     */
    private function formatPayment(
        Payment $payment
    ): array {
        return [
            'id' => $payment->id,

            'uuid' => $payment->uuid,

            'order_id' =>
                $payment->order_id,

            'gateway' =>
                $payment->gateway,

            'transaction_reference' =>
                $payment->transaction_reference,

            'status' =>
                $payment->status,

            'currency' =>
                strtoupper($payment->currency),

            'amount' =>
                number_format(
                    (float) $payment->amount,
                    2,
                    '.',
                    ''
                ),

            'paid_at' =>
                $payment->paid_at,

            'failed_at' =>
                $payment->failed_at,

            'created_at' =>
                $payment->created_at,
        ];
    }
}