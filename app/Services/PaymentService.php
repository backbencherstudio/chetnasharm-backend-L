<?php

namespace App\Services;

use App\Common\IntegrationConfig;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

class PaymentService
{
    public function __construct(
        private EnrollmentService $enrollmentService,
        private IntegrationConfig $integrationConfig,
    ) {}

    /**
     * @param  array{batch_id: int, payment_method: string}  $validated
     * @return array{
     *     type: 'batch_full'|'batch_started'|'already_enrolled'|'payment_response'|'error',
     *     expiry_date?: mixed,
     *     payment?: Payment,
     *     batch?: Batch,
     *     response?: array<string, mixed>,
     *     message?: string
     * }
     */
    public function createPayment(User $user, array $validated): array
    {
        DB::beginTransaction();

        try {
            $batch = Batch::with('class')->lockForUpdate()->findOrFail($validated['batch_id']);

            if ($batch->filled_seat >= $batch->total_seat) {
                DB::rollBack();

                return ['type' => 'batch_full'];
            }

            if ($batch->start_date && $batch->start_date->isPast()) {
                DB::rollBack();

                return ['type' => 'batch_started'];
            }

            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('batch_id', $batch->id)
                ->first();

            if ($enrollment) {
                DB::rollBack();

                return [
                    'type' => 'already_enrolled',
                    'expiry_date' => $enrollment->expiry_date,
                ];
            }

            $payment = Payment::where('user_id', $user->id)
                ->where('batch_id', $batch->id)
                ->latest()
                ->first();

            if ($payment && $payment->status !== 'paid') {
                $payment->update([
                    'payment_method' => $validated['payment_method'],
                    'amount' => $batch->class->price,
                ]);

                $payment->refresh();

                DB::commit();

                return [
                    'type' => 'payment_response',
                    'payment' => $payment,
                    'batch' => $batch,
                    'response' => $this->buildPaymentResponse($payment, $batch),
                ];
            }

            $payment = Payment::create([
                'payment_id' => $this->generatePaymentId(),
                'user_id' => $user->id,
                'batch_id' => $batch->id,
                'amount' => $batch->class->price,
                'currency' => 'USD',
                'payment_method' => $validated['payment_method'],
                'status' => 'pending',
            ]);

            DB::commit();

            return [
                'type' => 'payment_response',
                'payment' => $payment,
                'batch' => $batch,
                'response' => $this->buildPaymentResponse($payment, $batch),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'type' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPaymentResponse(Payment $payment, Batch $batch): array
    {
        if ($payment->payment_method === 'stripe') {
            return $this->createStripeCheckoutResponse($payment, $batch);
        }

        if ($payment->payment_method === 'paypal') {
            return $this->createPaypalCheckoutResponse($payment, $batch);
        }

        if ($payment->payment_method === 'token') {
            $setting = Setting::first();
            $support_number = $setting->support_number;
            $support_email = $setting->support_email;

            return [
                'status' => true,
                'message' => 'Contact support through whatsapp to complete payment and enrollment. Send payment ID for reference.',
                'payment_id' => $payment->payment_id,
                'support_number' => $support_number,
                'support_email' => $support_email,
            ];
        }

        return [
            'status' => false,
            'message' => 'Invalid payment method',
            'http_status' => 400,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createStripeCheckoutResponse(Payment $payment, Batch $batch): array
    {
        Stripe::setApiKey($this->integrationConfig->stripe()['secret']);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $batch->class->title,
                    ],
                    'unit_amount' => (int) round(((float) $payment->amount) * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => config('app.frontend_url')."/payment-success?payment_id={$payment->id}",
            'cancel_url' => config('app.frontend_url').'/payment-failed',
            'metadata' => [
                'payment_id' => $payment->id,
                'batch_id' => $batch->id,
            ],
        ]);

        return [
            'url' => $session->url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createPaypalCheckoutResponse(Payment $payment, Batch $batch): array
    {
        try {
            $token = $this->getPayPalToken();

            $client = new Client;

            $response = $client->post(
                $this->paypalBaseUrl().'/v2/checkout/orders',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => "Bearer {$token}",
                    ],
                    'json' => [
                        'intent' => 'CAPTURE',

                        'purchase_units' => [[
                            'reference_id' => (string) $payment->id,

                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => number_format($payment->amount, 2, '.', ''),
                            ],

                            'custom_id' => json_encode([
                                'payment_id' => $payment->id,
                                'batch_id' => $batch->id,
                            ]),
                        ]],

                        'application_context' => [
                            'brand_name' => config('app.name'),
                            'landing_page' => 'LOGIN',
                            'user_action' => 'PAY_NOW',

                            'return_url' => config('app.success_url'),
                            'cancel_url' => config('app.cancel_url'),
                        ],
                    ],
                ]
            );

            $data = json_decode($response->getBody(), true);

            if (empty($data['links']) || ! is_array($data['links'])) {
                return [
                    'status' => false,
                    'message' => 'Invalid PayPal response',
                    'http_status' => 500,
                ];
            }

            foreach ($data['links'] as $link) {
                if (($link['rel'] ?? null) === 'approve' && ! empty($link['href'])) {
                    return [
                        'status' => true,
                        'url' => $link['href'],
                        'order_id' => $data['id'] ?? null,
                    ];
                }
            }

            return [
                'status' => false,
                'message' => 'PayPal approval link not found',
                'http_status' => 500,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'PayPal checkout failed',
                'http_status' => 500,
            ];
        }
    }

    /**
     * @return array{type: 'redirect', url: string}
     */
    public function capturePaypalOrder(string $token): array
    {
        try {
            $accessToken = $this->getPayPalToken();

            $client = new Client;

            $response = $client->post(
                $this->paypalBaseUrl()."/v2/checkout/orders/{$token}/capture",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json',
                    ],
                ]
            );

            $result = json_decode($response->getBody(), true);

            if (($result['status'] ?? null) !== 'COMPLETED') {
                return [
                    'type' => 'redirect',
                    'url' => config('app.frontend_failed_url').'?reason=not_completed',
                ];
            }

            $purchaseUnit = $result['purchase_units'][0] ?? null;
            $capture = $purchaseUnit['payments']['captures'][0] ?? null;

            if (! $purchaseUnit || ! $capture) {
                return [
                    'type' => 'redirect',
                    'url' => config('app.frontend_failed_url').'?reason=invalid_structure',
                ];
            }

            $customId = $capture['custom_id'] ?? null;

            if (! $customId) {
                return [
                    'type' => 'redirect',
                    'url' => config('app.frontend_failed_url').'?reason=missing_metadata',
                ];
            }

            $data = json_decode($customId, true);

            if (! $data || ! isset($data['payment_id'], $data['batch_id'])) {
                return [
                    'type' => 'redirect',
                    'url' => config('app.frontend_failed_url').'?reason=invalid_metadata',
                ];
            }

            DB::beginTransaction();

            try {
                $payment = Payment::lockForUpdate()
                    ->findOrFail($data['payment_id']);

                if ((int) $data['batch_id'] !== (int) $payment->batch_id) {
                    throw new \Exception('Batch mismatch detected');
                }

                if ($payment->status === 'paid') {
                    $this->enrollmentService->enrollFromPayment($payment, null, false);
                    DB::commit();

                    return [
                        'type' => 'redirect',
                        'url' => config('app.frontend_success_url').'?payment_id='.$payment->id.'&status=already_processed',
                    ];
                }

                $expectedAmount = number_format((float) $payment->amount, 2, '.', '');
                $paypalAmount = number_format((float) ($capture['amount']['value'] ?? 0), 2, '.', '');

                if ($expectedAmount !== $paypalAmount) {
                    throw new \Exception('Amount mismatch detected');
                }

                $payment->update([
                    'status' => 'paid',
                    'transaction_id' => $capture['id'],
                    'paid_at' => now(),
                ]);

                $this->enrollmentService->enrollFromPayment($payment, null, false);

                DB::commit();

                return [
                    'type' => 'redirect',
                    'url' => config('app.frontend_success_url').'?payment_id='.$payment->id.'&status=success',
                ];
            } catch (\Throwable $e) {
                DB::rollBack();

                return [
                    'type' => 'redirect',
                    'url' => config('app.frontend_failed_url').'?reason=processing_failed',
                ];
            }
        } catch (\Throwable $e) {
            return [
                'type' => 'redirect',
                'url' => config('app.frontend_failed_url').'?reason=api_error',
            ];
        }
    }

    /**
     * @return array{type: 'redirect', url: string}
     */
    public function paypalCancelRedirect(): array
    {
        return [
            'type' => 'redirect',
            'url' => config('app.frontend_cancel_url').'?status=cancelled',
        ];
    }

    /**
     * @return array{
     *     type: 'invalid_webhook'|'invalid_metadata'|'payment_not_found'|'already_processed'|'processing_failed'|'success'|'missing_metadata',
     *     payload?: array<string, mixed>,
     *     http_status?: int
     * }
     */
    public function handleStripeWebhook(string $payload, ?string $sigHeader): array
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->integrationConfig->stripe()['webhook_secret']
            );
        } catch (\Throwable $e) {
            return [
                'type' => 'invalid_webhook',
                'http_status' => 400,
            ];
        }

        $type = $event->type;
        $session = $event->data->object;

        if ($type === 'checkout.session.completed') {
            if (
                empty($session->metadata->payment_id) ||
                empty($session->metadata->batch_id)
            ) {
                return [
                    'type' => 'invalid_metadata',
                    'http_status' => 400,
                ];
            }

            DB::beginTransaction();

            try {
                $payment = Payment::lockForUpdate()->find($session->metadata->payment_id);

                if (! $payment) {
                    DB::rollBack();

                    return [
                        'type' => 'payment_not_found',
                        'http_status' => 404,
                    ];
                }

                if ((int) $session->metadata->batch_id !== (int) $payment->batch_id) {
                    throw new \Exception('Batch mismatch');
                }

                if ($payment->status === 'paid') {
                    $this->enrollmentService->enrollFromPayment($payment, null, false);
                    DB::commit();

                    return [
                        'type' => 'already_processed',
                        'payload' => ['status' => 'already processed'],
                    ];
                }

                if ($payment->transaction_id === ($session->payment_intent ?? $session->id)) {
                    DB::commit();

                    return [
                        'type' => 'already_processed',
                        'payload' => ['status' => 'already processed'],
                    ];
                }

                $expectedCents = (int) round(((float) $payment->amount) * 100);
                $actualCents = (int) ($session->amount_total ?? 0);

                if ($expectedCents !== $actualCents) {
                    throw new \Exception('Amount mismatch');
                }

                $payment->update([
                    'status' => 'paid',
                    'transaction_id' => $session->payment_intent ?? $session->id,
                    'paid_at' => now(),
                ]);

                $this->enrollmentService->enrollFromPayment($payment, null, false);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                Log::error('Stripe webhook error', [
                    'error' => $e->getMessage(),
                    'payload' => $payload,
                ]);

                return [
                    'type' => 'processing_failed',
                    'http_status' => 500,
                ];
            }
        }

        if ($type === 'checkout.session.async_payment_failed') {
            $payment = Payment::find($session->metadata->payment_id ?? null);

            if ($payment) {
                $payment->update([
                    'status' => 'failed',
                ]);
            }
        }

        if ($type === 'checkout.session.expired') {
            $session = $event->data->object;

            if (empty($session->metadata->payment_id)) {
                return [
                    'type' => 'missing_metadata',
                    'http_status' => 400,
                ];
            }

            $payment = Payment::find($session->metadata->payment_id);

            if (! $payment) {
                return [
                    'type' => 'success',
                    'payload' => ['status' => 'success'],
                ];
            }

            if ($payment->status === 'pending') {
                $payment->update([
                    'status' => 'failed',
                ]);
            }
        }

        return [
            'type' => 'success',
            'payload' => ['status' => 'success'],
        ];
    }

    private function paypalBaseUrl(): string
    {
        $paypal = $this->integrationConfig->paypal();
        $baseUrl = $paypal['base_url'];

        if (filled($baseUrl)) {
            return rtrim((string) $baseUrl, '/');
        }

        return $paypal['mode'] === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function getPayPalToken(): string
    {
        $client = new Client;
        $paypal = $this->integrationConfig->paypal();

        $response = $client->post($this->paypalBaseUrl().'/v1/oauth2/token', [
            'auth' => [
                $paypal['client_id'],
                $paypal['client_secret'],
            ],
            'form_params' => [
                'grant_type' => 'client_credentials',
            ],
        ]);

        return json_decode($response->getBody(), true)['access_token'];
    }

    private function generatePaymentId(): int
    {
        do {
            $paymentId = rand(100000, 999999);
        } while (Payment::where('payment_id', $paymentId)->exists());

        return $paymentId;
    }
}
