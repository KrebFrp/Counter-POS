<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->username)) {
            $this->merge(['username' => strtolower(trim($this->username))]);
        }
    }

    public function rules(): array
    {
        return ['username' => ['required', 'string', 'max:50'], 'password' => ['required', 'string']];
    }

    public function authenticate(): void
    {
        $key = hash('sha256', $this->string('username').'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! Auth::attempt($this->safe()->only('username', 'password'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['username' => 'The supplied credentials are incorrect.']);
        }

        RateLimiter::clear($key);
    }
}
