<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentRequest;
use App\Http\Requests\Payment\PaypalCaptureRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payment) {}

    /** Create a payment session for a batch enrollment. */
    public function createPayment(CreatePaymentRequest $request): JsonResponse
    {
        $result = $this->payment->createPayment(
            auth('api')->user(),
            $request->validated()
        );

        if ($result['type'] === 'batch_full') {
            return response()->json([
                'status' => false,
                'message' => 'Batch is full',
            ], 400);
        }

        if ($result['type'] === 'batch_started') {
            return response()->json([
                'status' => false,
                'message' => 'Batch has already started',
            ], 400);
        }

        if ($result['type'] === 'already_enrolled') {
            return response()->json([
                'status' => false,
                'message' => 'Already enrolled and active',
                'expiry_date' => $result['expiry_date'],
            ], 409);
        }

        if ($result['type'] === 'error') {
            return response()->json([
                'status' => false,
                'message' => 'Payment creation failed',
                'error' => $result['message'],
            ], 500);
        }

        $response = $result['response'];
        $httpStatus = $response['http_status'] ?? 200;
        unset($response['http_status']);

        return response()->json($response, $httpStatus);
    }

    /** Capture an approved PayPal payment. */
    public function paypalCapture(PaypalCaptureRequest $request): RedirectResponse
    {
        $result = $this->payment->capturePaypalOrder($request->validated('token'));

        return redirect()->away($result['url']);
    }

    /** Handle a cancelled PayPal checkout. */
    public function paypalCancel(Request $request): RedirectResponse
    {
        $result = $this->payment->paypalCancelRedirect();

        return redirect()->away($result['url']);
    }
}
