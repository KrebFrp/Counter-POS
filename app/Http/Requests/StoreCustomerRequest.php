<?php

namespace App\Http\Requests;

class StoreCustomerRequest extends ManagementRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
