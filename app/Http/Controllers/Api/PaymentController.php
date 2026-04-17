<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'payment_method' => 'required|in:stripe,paypal,token',
        ]);

        $user = auth('api')->user();

        $batch = Batch::with('class')->findOrFail($request->batch_id);

        if ($batch->filled_seat >= $batch->total_seat) {
            return response()->json([
                'status' => false,
                'message' => 'Batch is full'
            ], 400);
        }

        if ($batch->start_date && $batch->start_date->isPast()) {
            return response()->json([
                'status' => false,
                'message' => 'Batch has already started'
            ], 400);
        }

        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('batch_id', $batch->id)
            ->first();

        if ($enrollment) {
            return response()->json([
                'status' => false,
                'message' => 'Already enrolled and active',
                'expiry_date' => $enrollment->expiry_date
            ], 409);
        }

        DB::beginTransaction();

        try {

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

            if ($payment && $payment->status === 'paid') {

                if ($enrollment && $enrollment->expiry_date && $enrollment->expiry_date->isFuture()) {
                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'Already enrolled'
                    ], 409);
                }
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
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function handlePayment($payment, $batch)
    {
        if ($payment->payment_method === 'stripe') {
            return $this->stripeCheckout($payment, $batch);
        }

        if ($payment->payment_method === 'paypal') {
            return $this->paypalCheckout($payment, $batch);
        }

        if ($payment->payment_method === 'token') {

            $support_number = Setting::first()->support_number;

            return response()->json([
                'status' => true,
                'message' => 'Contact support through whatsapp to complete payment and enrollment. Send payment ID for reference.',
                'payment_id' => $payment->payment_id,
                'support_number' => $support_number
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid payment method'
        ], 400);
    }


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
                    'unit_amount' => $payment->amount * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => config('app.frontend_url') . "/payment-success?payment_id={$payment->id}",
            'cancel_url' => config('app.frontend_url') . "/payment-failed",
            'metadata' => [
                'payment_id' => $payment->id,
                'batch_id' => $batch->id,
            ],
        ]);

        return response()->json([
            'url' => $session->url
        ]);
    }

    private function getPayPalToken()
    {
        $client = new Client();

        $response = $client->post('https://api-m.sandbox.paypal.com/v1/oauth2/token', [
            'auth' => [
                config('services.paypal.client_id'),
                config('services.paypal.client_secret')
            ],
            'form_params' => [
                'grant_type' => 'client_credentials'
            ]
        ]);

        return json_decode($response->getBody(), true)['access_token'];
    }

    public function paypalCheckout($payment, $batch)
    {
        try {
            $token = $this->getPayPalToken();

            $client = new Client();

            $response = $client->post(
                'https://api-m.sandbox.paypal.com/v2/checkout/orders',
                [
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => "Bearer {$token}",
                    ],
                    'json' => [
                        "intent" => "CAPTURE",

                        "purchase_units" => [[
                            "reference_id" => (string) $payment->id,

                            "amount" => [
                                "currency_code" => "USD",
                                "value" => number_format($payment->amount, 2, '.', '')
                            ],

                            "custom_id" => json_encode([
                                'payment_id' => $payment->id,
                                'batch_id'   => $batch->id
                            ]),
                        ]],

                        "application_context" => [
                            "brand_name" => config('app.name'),
                            "landing_page" => "LOGIN",
                            "user_action" => "PAY_NOW",

                            "return_url" => config('app.frontend_url') . "/paypal-success",
                            "cancel_url" => config('app.frontend_url') . "/payment-failed",
                        ]
                    ]
                ]
            );

            $data = json_decode($response->getBody(), true);

            if (empty($data['links']) || !is_array($data['links'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid PayPal response'
                ], 500);
            }

            foreach ($data['links'] as $link) {
                if (($link['rel'] ?? null) === 'approve' && !empty($link['href'])) {
                    return response()->json([
                        'status' => true,
                        'url' => $link['href'],
                        'order_id' => $data['id'] ?? null
                    ]);
                }
            }

            return response()->json([
                'status' => false,
                'message' => 'PayPal approval link not found'
            ], 500);

        } catch (\Throwable $e) {

            Log::error('PayPal Checkout Error', [
                'payment_id' => $payment->id ?? null,
                'batch_id'   => $batch->id ?? null,
                'error'      => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'PayPal checkout failed'
            ], 500);
        }
    }

    public function paypalCapture(Request $request)
    {
        $request->validate([
            'token' => 'required'
        ]);

        try {
            $token = $this->getPayPalToken();

            $client = new Client();

            $response = $client->post(
                "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$request->token}/capture",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Content-Type'  => 'application/json',
                    ]
                ]
            );

            $result = json_decode($response->getBody(), true);

            if (($result['status'] ?? null) !== 'COMPLETED') {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment not completed',
                    'paypal_status' => $result['status'] ?? null
                ], 400);
            }

            $purchaseUnit = $result['purchase_units'][0] ?? null;

            if (!$purchaseUnit || empty($purchaseUnit['custom_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid PayPal response structure'
                ], 400);
            }

            $data = json_decode($purchaseUnit['custom_id'], true);

            if (!$data || !isset($data['payment_id'], $data['batch_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid PayPal metadata'
                ], 400);
            }

            DB::beginTransaction();

            try {
                $payment = Payment::lockForUpdate()->findOrFail($data['payment_id']);

                if ($payment->status === 'paid') {
                    DB::commit();

                    return response()->json([
                        'status' => true,
                        'message' => 'Already processed'
                    ]);
                }

                $paypalAmount = $purchaseUnit['amount']['value'] ?? null;

                if ((float) $payment->amount !== (float) $paypalAmount) {
                    throw new \Exception('Amount mismatch detected');
                }

                $payment->update([
                    'status' => 'paid',
                    'transaction_id' => $result['id'],
                    'paid_at' => now(),
                ]);

                $this->createEnrollment($payment, $data['batch_id']);

                DB::commit();

                return response()->json([
                    'status'  => true,
                    'message' => 'Payment successful'
                ]);

            } catch (\Throwable $e) {
                DB::rollBack();

                return response()->json([
                    'status'  => false,
                    'message' => 'Payment processing failed',
                    'error'   => $e->getMessage()
                ], 500);
            }

        } catch (\Throwable $e) {

            return response()->json([
                'status'  => false,
                'message' => 'PayPal API error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function createEnrollment($payment, $batchId)
    {
        return DB::transaction(function () use ($payment, $batchId) {

            $batch = Batch::lockForUpdate()->findOrFail($batchId);

            $exists = Enrollment::where('user_id', $payment->user_id)
                ->where('batch_id', $batchId)
                ->exists();

            if ($exists) {
                return null;
            }

            if ($batch->filled_seat >= $batch->total_seat) {
                throw new \Exception('Batch is full');
            }

            $enrollment = Enrollment::create([
                'user_id' => $payment->user_id,
                'batch_id' => $batch->id,
                'class_id' => $batch->class_id,
                'status' => 'active',
                'enrolled_at' => now(),
                'expiry_date' => $batch->end_date ? $batch->end_date : null,
            ]);

            $batch->increment('filled_seat');

            $payment->update([
                'enrollment_id' => $enrollment->id
            ]);

            return $enrollment;
        });
    }

    private function generatePaymentId()
    {
        do {
            $paymentId = rand(100000, 999999);
        } while (Payment::where('payment_id', $paymentId)->exists());

        return $paymentId;
    }

}
