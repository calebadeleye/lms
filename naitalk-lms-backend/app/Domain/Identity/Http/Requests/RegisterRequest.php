<?php

namespace App\Domain\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    private const ACK_KEYS = [
        'ack_impact_beyond_earning', 'ack_growth_mindset', 'ack_interest_in_coaching', 'ack_positive_impact',
    ];

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
            // The 4 membership requirements, mirroring the client's manual
            // Google Form — but only one needs to be true, not all four (see
            // withValidator() below for the actual "at least one" check).
            'ack_impact_beyond_earning' => ['nullable', 'boolean'],
            'ack_growth_mindset' => ['nullable', 'boolean'],
            'ack_interest_in_coaching' => ['nullable', 'boolean'],
            'ack_positive_impact' => ['nullable', 'boolean'],
            'motivation' => ['nullable', 'string', 'max:1000'],
            // Optional — see MembershipApplication; the review queue flags
            // photo-less applications rather than blocking signup on them.
            'photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $anyAcknowledged = collect(self::ACK_KEYS)->contains(fn (string $key) => $this->boolean($key));

            if (! $anyAcknowledged) {
                $validator->errors()->add('ack_impact_beyond_earning', 'Please confirm at least one of the membership requirements.');
            }
        });
    }
}
