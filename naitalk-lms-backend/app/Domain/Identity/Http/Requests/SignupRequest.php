<?php

namespace App\Domain\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Plain learner sign-up — just the account fields. Joining the HR GEMs
 * Coach Network (RegisterRequest) is a separate, application-based flow.
 */
class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'device_label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
