<?php

namespace App\Http\Requests\Transaction;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class MarkAsPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $payment = Payment::find($this->route('id'));

        if (! $payment || $payment->status === 'paid') {
            return [];
        }

        return [
            'transaction_id' => ['required', 'string', 'unique:payments,transaction_id'],
        ];
    }
}
