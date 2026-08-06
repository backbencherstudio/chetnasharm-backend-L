<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends BaseController
{
    public function __construct(private PaymentService $payment) {}

    /** Handle Stripe webhook events. */
    public function stripeWebhook(Request $request): JsonResponse
    {
        $result = $this->payment->handleStripeWebhook(
            $request->getContent(),
            $request->header('Stripe-Signature')
        );

        if ($result['type'] === 'invalid_webhook') {
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        if ($result['type'] === 'invalid_metadata') {
            return response()->json(['error' => 'Invalid metadata'], 400);
        }

        if ($result['type'] === 'payment_not_found') {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        if ($result['type'] === 'processing_failed') {
            return response()->json(['error' => 'Processing failed'], 500);
        }

        if ($result['type'] === 'missing_metadata') {
            return response()->json(['error' => 'Missing metadata'], 400);
        }

        if ($result['type'] === 'already_processed') {
            return response()->json($result['payload']);
        }

        return response()->json($result['payload']);
    }
}
