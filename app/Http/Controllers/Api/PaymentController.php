<?php

namespace App\Http\Controllers\Api;

use App\Common\EnrollStudentFromPayment;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class PaymentController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(private EnrollStudentFromPayment $enrollStudentFromPayment) {}

    /**
     * Create a payment session for a batch enrollment.
     *
     * @return JsonResponse
     */
    public function createPayment(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'payment_method' => 'required|in:stripe,paypal,token',
        ]);

        $user = auth('api')->user();

        DB::beginTransaction();

        try {
            $batch = Batch::with('class')->lockForUpdate()->findOrFail($request->batch_id);

            if ($batch->filled_seat >= $batch->total_seat) {
                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Batch is full',
                ], 400);
            }

            if ($batch->start_date && $batch->start_date->isPast()) {
                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Batch has already started',
                ], 400);
            }

            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('batch_id', $batch->id)
                ->first();

            if ($enrollment) {
                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Already enrolled and active',
                    'expiry_date' => $enrollment->expiry_date,
                ], 409);
            }

            $payment = Payment::where('user_id', $user->id)
                ->where('batch_id', $batch->id)
                ->latest()
                ->first();

            if ($payment && $payment->status !== 'paid') {

                $payment->update([
                    'payment_method' => $request->payment_method,
                    'amount' => $batch->class->price,
                ]);

                $payment->refresh();

                DB::commit();

                return $this->handlePayment($payment, $batch);
            }

            $payment = Payment::create([
                'payment_id' => $this->generatePaymentId(),
                'user_id' => $user->id,
                'batch_id' => $batch->id,
                'amount' => $batch->class->price,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'status' => 'pending',
            ]);

            DB::commit();

            return $this->handlePayment($payment, $batch);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Payment creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Route the payment to the selected gateway.
     *
     * @return JsonResponse
     */
    private function handlePayment($payment, $batch)
    {
        if ($payment->payment_method === 'stripe') {
            return $this->stripeCheckout($payment, $batch);
        }

        if ($payment->payment_method === 'paypal') {
            return $this->paypalCheckout($payment, $batch);
        }

        if ($payment->payment_method === 'token') {

            $setting = Setting::first();
            $support_number = $setting->support_number;
            $support_email = $setting->support_email;

            return response()->json([
                'status' => true,
                'message' => 'Contact support through whatsapp to complete payment and enrollment. Send payment ID for reference.',
                'payment_id' => $payment->payment_id,
                'support_number' => $support_number,
                'support_email' => $support_email,
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid payment method',
        ], 400);
    }

    /**
     * Create a Stripe checkout session.
     *
     * @return JsonResponse
     */
    public function stripeCheckout($payment, $batch)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

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

        return response()->json([
            'url' => $session->url,
        ]);
    }

    /**
     * Resolve the PayPal API base URL.
     */
    private function paypalBaseUrl(): string
    {
        $baseUrl = config('services.paypal.base_url');

        if (filled($baseUrl)) {
            return rtrim((string) $baseUrl, '/');
        }

        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Fetch a PayPal OAuth access token.
     *
     * @return mixed
     */
    private function getPayPalToken()
    {
        $client = new Client;

        $response = $client->post($this->paypalBaseUrl().'/v1/oauth2/token', [
            'auth' => [
                config('services.paypal.client_id'),
                config('services.paypal.client_secret'),
            ],
            'form_params' => [
                'grant_type' => 'client_credentials',
            ],
        ]);

        return json_decode($response->getBody(), true)['access_token'];
    }

    /**
     * Create a PayPal checkout order.
     *
     * @return JsonResponse
     */
    public function paypalCheckout($payment, $batch)
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
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid PayPal response',
                ], 500);
            }

            foreach ($data['links'] as $link) {
                if (($link['rel'] ?? null) === 'approve' && ! empty($link['href'])) {
                    return response()->json([
                        'status' => true,
                        'url' => $link['href'],
                        'order_id' => $data['id'] ?? null,
                    ]);
                }
            }

            return response()->json([
                'status' => false,
                'message' => 'PayPal approval link not found',
            ], 500);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'PayPal checkout failed',
            ], 500);
        }
    }

    /**
     * Capture an approved PayPal payment.
     *
     * @return RedirectResponse
     */
    public function paypalCapture(Request $request)
    {
        $request->validate([
            'token' => 'required',
        ]);

        try {
            $accessToken = $this->getPayPalToken();

            $client = new Client;

            $response = $client->post(
                $this->paypalBaseUrl()."/v2/checkout/orders/{$request->token}/capture",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json',
                    ],
                ]
            );

            $result = json_decode($response->getBody(), true);

            if (($result['status'] ?? null) !== 'COMPLETED') {
                return redirect()->away(
                    config('app.frontend_failed_url').'?reason=not_completed'
                );
            }

            $purchaseUnit = $result['purchase_units'][0] ?? null;
            $capture = $purchaseUnit['payments']['captures'][0] ?? null;

            if (! $purchaseUnit || ! $capture) {
                return redirect()->away(
                    config('app.frontend_failed_url').'?reason=invalid_structure'
                );
            }

            $customId = $capture['custom_id'] ?? null;

            if (! $customId) {
                return redirect()->away(
                    config('app.frontend_failed_url').'?reason=missing_metadata'
                );
            }

            $data = json_decode($customId, true);

            if (! $data || ! isset($data['payment_id'], $data['batch_id'])) {
                return redirect()->away(
                    config('app.frontend_failed_url').'?reason=invalid_metadata'
                );
            }

            DB::beginTransaction();

            try {

                $payment = Payment::lockForUpdate()
                    ->findOrFail($data['payment_id']);

                if ((int) $data['batch_id'] !== (int) $payment->batch_id) {
                    throw new \Exception('Batch mismatch detected');
                }

                if ($payment->status === 'paid') {
                    $this->enrollStudentFromPayment->handle($payment, null, false);
                    DB::commit();

                    return redirect()->away(
                        config('app.frontend_success_url').'?payment_id='.$payment->id.'&status=already_processed'
                    );
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

                $this->enrollStudentFromPayment->handle($payment, null, false);

                DB::commit();

                return redirect()->away(
                    config('app.frontend_success_url').'?payment_id='.$payment->id.'&status=success'
                );

            } catch (\Throwable $e) {

                DB::rollBack();

                return redirect()->away(
                    config('app.frontend_failed_url').'?reason=processing_failed'
                );
            }

        } catch (\Throwable $e) {

            return redirect()->away(
                config('app.frontend_failed_url').'?reason=api_error'
            );
        }
    }

    /**
     * Generate a unique payment reference ID.
     *
     * @return mixed
     */
    private function generatePaymentId()
    {
        do {
            $paymentId = rand(100000, 999999);
        } while (Payment::where('payment_id', $paymentId)->exists());

        return $paymentId;
    }

    /**
     * Handle a cancelled PayPal checkout.
     *
     * @return RedirectResponse
     */
    public function paypalCancel(Request $request)
    {
        return redirect()->away(
            config('app.frontend_cancel_url').'?status=cancelled'
        );
    }
}
