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
    public function __construct(private readonly PaymentService $payment) {}

    /** Create a payment session for a batch enrollment. */
    public function createPayment(CreatePaymentRequest $request): JsonResponse
    {
        $result = $this->payment->createPayment(
            auth('api')->user(),
            $request->validated()
        );

        if ($result['type'] === 'batch_full') {
            return $this->error('Batch is full', 400);
        }

        if ($result['type'] === 'batch_started') {
            return $this->error('Batch has already started', 400);
        }

        if ($result['type'] === 'already_enrolled') {
            return $this->error(
                'Already enrolled and active',
                409,
                [],
                ['expiry_date' => $result['expiry_date']]
            );
        }

        if ($result['type'] === 'error') {
            return $this->error('Payment creation failed', 500, ['error' => $result['message']]);
        }

        $response = $result['response'];
        $httpStatus = $response['http_status'] ?? 200;
        unset($response['http_status']);

        $response['success'] = $response['status'] ?? true;

        return $this->respond($response, $httpStatus);
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
