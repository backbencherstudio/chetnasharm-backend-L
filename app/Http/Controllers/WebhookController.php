<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends BaseController
{
    public function __construct(private readonly PaymentService $payment) {}

    /** Handle Stripe webhook events. */
    public function stripeWebhook(Request $request): JsonResponse
    {
        $result = $this->payment->handleStripeWebhook(
            $request->getContent(),
            $request->header('Stripe-Signature')
        );

        if ($result['type'] === 'invalid_webhook') {
            return $this->respond(['error' => 'Invalid webhook'], 400);
        }

        if ($result['type'] === 'invalid_metadata') {
            return $this->respond(['error' => 'Invalid metadata'], 400);
        }

        if ($result['type'] === 'payment_not_found') {
            return $this->respond(['error' => 'Payment not found'], 404);
        }

        if ($result['type'] === 'processing_failed') {
            return $this->respond(['error' => 'Processing failed'], 500);
        }

        if ($result['type'] === 'missing_metadata') {
            return $this->respond(['error' => 'Missing metadata'], 400);
        }

        return $this->respond($result['payload']);
    }
}
