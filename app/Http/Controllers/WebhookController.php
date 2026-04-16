<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Batch;

class WebhookController extends Controller
{
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
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

            $payment = Payment::find($session->metadata->payment_id);

            if (!$payment) {
                return response()->json(['error' => 'Payment not found'], 404);
            }

            if ($payment->status === 'paid') {
                return response()->json(['status' => 'already processed']);
            }

            DB::beginTransaction();

            try {

                $stripeAmount = $session->amount_total / 100;

                if ((float)$payment->amount !== (float)$stripeAmount) {
                    throw new \Exception('Amount mismatch');
                }

                $payment->update([
                    'status' => 'paid',
                    'transaction_id' => $session->payment_intent ?? $session->id,
                    'paid_at' => now(),
                ]);

                $this->createEnrollment($payment, $session->metadata->batch_id);

                DB::commit();

            } catch (\Throwable $e) {
                DB::rollBack();
            }
        }

        if ($type === 'checkout.session.async_payment_failed') {

            $payment = Payment::find($session->metadata->payment_id ?? null);

            if ($payment) {
                $payment->update([
                    'status' => 'failed'
                ]);
            }
        }

        if ($type === 'checkout.session.expired') {

            $session = $event->data->object;

            if (empty($session->metadata->payment_id)) {
                return response()->json(['error' => 'Missing metadata'], 400);
            }

            $payment = Payment::find($session->metadata->payment_id);

            if (!$payment) {
                return;
            }

            if ($payment->status === 'pending') {
                $payment->update([
                    'status' => 'failed'
                ]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    private function createEnrollment($payment, $batchId)
    {
        $batch = Batch::with('class')->findOrFail($batchId);

        $exists = Enrollment::where('user_id', $payment->user_id)
            ->where('batch_id', $batchId)
            ->exists();

        if ($exists) {
            return;
        }

        $enrollment = Enrollment::create([
            'user_id' => $payment->user_id,
            'batch_id' => $batch->id,
            'class_id' => $batch->class_id,
            'status' => 'active',
            'enrolled_at' => now(),
            'expiry_date' => now()->addDays($batch->class->duration_in_days),
        ]);

        if ($batch->filled_seat < $batch->total_seat) {
            $batch->increment('filled_seat');
        }

        $payment->update([
            'enrollment_id' => $enrollment->id
        ]);
    }
}
