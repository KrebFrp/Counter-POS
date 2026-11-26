<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreSaleRequest extends ManagementRequest
{
    public function attributes(): array
    {
        return [
            'submission_id' => 'checkout reference',
            'items' => 'cart',
            'items.*' => 'cart item',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'product_id' => 'product',
            'customer_id' => 'customer',
            'payment_method' => 'payment method',
            'amount_tendered' => 'cash received',
        ];
    }

    public function messages(): array
    {
        return ['items.*.product_id.distinct' => 'Each product can appear only once. Update its quantity instead.'];
    }

    public function rules(): array
    {
        return [
            'submission_id' => ['required', 'uuid'],
            'items' => ['required_without:product_id', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'product_id' => ['required_without:items', Rule::prohibitedIf(fn (): bool => $this->has('items')), 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['required_without:items', Rule::prohibitedIf(fn (): bool => $this->has('items')), 'integer', 'min:1', 'max:2147483647'],
            'payment_method' => ['required_with:items', Rule::in(['cash', 'card', 'ewallet'])],
            'amount_tendered' => ['required_if:payment_method,cash', 'prohibited_unless:payment_method,cash', 'nullable', 'numeric', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D', 'min:0', 'max:99999999.99'],
        ];
    }
}
