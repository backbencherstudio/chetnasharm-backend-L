<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

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
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        if ($event->type === 'checkout.session.completed') {

            $session = $event->data->object;

            $payment = Payment::find($session->metadata->payment_id);

            if ($payment && $payment->status !== 'paid') {

                DB::beginTransaction();

                try {

                    $payment->update([
                        'status' => 'paid',
                        'transaction_id' => $session->payment_intent,
                        'paid_at' => now(),
                    ]);

                    $this->createEnrollment($payment, $session->metadata->batch_id);

                    DB::commit();

                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::error('Stripe webhook error', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    private function createEnrollment($payment, $batchId)
    {
        $batch = Batch::with('class')->findOrFail($batchId);

        if (Enrollment::where('user_id', $payment->user_id)
            ->where('batch_id', $batchId)->exists()) {
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

        $batch->increment('filled_seat');

        $payment->update([
            'enrollment_id' => $enrollment->id
        ]);
    }
}
