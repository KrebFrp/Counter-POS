<?php

namespace App\Http\Requests;

class StoreProductRequest extends ManagementRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'image' => $this->imageRules(),
        ];
    }
}
