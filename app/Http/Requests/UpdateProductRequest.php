<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'original_stock' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'stock_reason' => [Rule::requiredIf(fn (): bool => (int) $this->input('stock_quantity') !== (int) $this->input('original_stock')), 'nullable', 'string', 'max:500'],
        ];
    }
}
