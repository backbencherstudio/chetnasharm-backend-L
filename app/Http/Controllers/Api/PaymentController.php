<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class PaymentController extends Controller
{

    public function createPayment(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'payment_method' => 'required|in:stripe,paypal,manual',
        ]);

        $user = auth('api')->user();
        $batch = Batch::with('class')->findOrFail($request->batch_id);

        if ($batch->filled_seat >= $batch->total_seat) {
            return response()->json([
                'status' => false,
                'message' => 'Batch is full'
            ], 400);
        }

        $already = Enrollment::where('user_id', $user->id)
            ->where('batch_id', $batch->id)
            ->exists();

        if ($already) {
            return response()->json([
                'status' => false,
                'message' => 'Already enrolled'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $batch->class->price,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'status' => 'pending',
            ]);

            DB::commit();

            if ($request->payment_method === 'stripe') {
                return $this->stripeCheckout($payment, $batch);
            }

            if ($request->payment_method === 'paypal') {
                return $this->paypalCheckout($payment, $batch);
            }

            return response()->json([
                'status' => true,
                'message' => 'Manual payment initiated',
                'payment_id' => $payment->id
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Payment creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
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

    public function paypalCheckout($payment, $batch)
    {
        return response()->json([
            'url' => 'https://www.paypal.com/checkout?payment_id=' . $payment->id
        ]);
    }

}
