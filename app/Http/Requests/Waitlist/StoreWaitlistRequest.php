<?php

namespace App\Http\Requests\Waitlist;

use Illuminate\Foundation\Http\FormRequest;

class StoreWaitlistRequest extends FormRequest
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
        return [
            'batch_id' => 'required|exists:batches,id',
        ];
    }
}
