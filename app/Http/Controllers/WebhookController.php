<?php

namespace App\Http\Controllers;

use App\Common\EnrollStudentFromPayment;
use App\Common\IntegrationConfig;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class WebhookController extends Controller
{
    /** Inject payment enrollment and integration dependencies. */
    public function __construct(
        private EnrollStudentFromPayment $enrollStudentFromPayment,
        private IntegrationConfig $integrationConfig,
    ) {}

    /** Handle Stripe webhook events. */
    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->integrationConfig->stripe()['webhook_secret']
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        $type = $event->type;
        $session = $event->data->object;

        if ($type === 'checkout.session.completed') {

            if (
                empty($session->metadata->payment_id) ||
                empty($session->metadata->batch_id)
            ) {
                return response()->json(['error' => 'Invalid metadata'], 400);
            }

            DB::beginTransaction();

            try {
                $payment = Payment::lockForUpdate()->find($session->metadata->payment_id);

                if (! $payment) {
                    DB::rollBack();

                    return response()->json(['error' => 'Payment not found'], 404);
                }

                if ((int) $session->metadata->batch_id !== (int) $payment->batch_id) {
                    throw new \Exception('Batch mismatch');
                }

                if ($payment->status === 'paid') {
                    $this->enrollStudentFromPayment->handle($payment, null, false);
                    DB::commit();

                    return response()->json(['status' => 'already processed']);
                }

                if ($payment->transaction_id === ($session->payment_intent ?? $session->id)) {
                    DB::commit();

                    return response()->json(['status' => 'already processed']);
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

                $this->enrollStudentFromPayment->handle($payment, null, false);

                DB::commit();

            } catch (\Throwable $e) {
                DB::rollBack();

                Log::error('Stripe webhook error', [
                    'error' => $e->getMessage(),
                    'payload' => $payload,
                ]);

                return response()->json(['error' => 'Processing failed'], 500);
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
                return response()->json(['error' => 'Missing metadata'], 400);
            }

            $payment = Payment::find($session->metadata->payment_id);

            if (! $payment) {
                return response()->json(['status' => 'success']);
            }

            if ($payment->status === 'pending') {
                $payment->update([
                    'status' => 'failed',
                ]);
            }
        }

        return response()->json(['status' => 'success']);
    }
}
