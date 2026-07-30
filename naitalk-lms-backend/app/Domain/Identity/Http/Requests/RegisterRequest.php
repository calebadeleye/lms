<?php

namespace App\Domain\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            // The 4 membership requirements — each must be explicitly
            // acknowledged, mirroring the client's manual Google Form.
            'ack_impact_beyond_earning' => ['required', 'accepted'],
            'ack_growth_mindset' => ['required', 'accepted'],
            'ack_interest_in_coaching' => ['required', 'accepted'],
            'ack_positive_impact' => ['required', 'accepted'],
            'motivation' => ['nullable', 'string', 'max:1000'],
            // Optional — see MembershipApplication; the review queue flags
            // photo-less applications rather than blocking signup on them.
            'photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ];
    }
}
