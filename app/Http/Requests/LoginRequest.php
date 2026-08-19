<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            // Lets a token be scoped to a client (e.g. "web-dashboard"), purely
            // for identifying it later in a token list -- not a security boundary.
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
