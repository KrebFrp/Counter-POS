<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends ManagementRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'submission_id' => ['required', 'uuid'],
            'type' => ['required', Rule::in(['restock', 'removal'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
