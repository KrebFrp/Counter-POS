<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreStaffRequest extends ManagementRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->username)) {
            $this->merge(['username' => strtolower(trim($this->username))]);
        }
    }

    public function rules(): array
    {
        $unique = Rule::unique('users', 'username');
        if ($this->route('user')) {
            $unique->ignore($this->route('user')->id);
        }

        return [
            'role' => ['sometimes', 'required', Rule::in(['admin', 'cashier'])],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_.-]+$/D', $unique],
            'full_name' => ['required', 'string', 'max:100'],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'min:8', 'confirmed', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('The password must not exceed 72 bytes.');
                }
            }],
            'avatar' => $this->imageRules(),
        ];
    }
}
