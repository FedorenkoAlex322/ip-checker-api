<?php

namespace App\Http\Requests;

final class EmailLookupRequest extends ApiFormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'email:rfc,dns'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'target.required' => 'The email address is required.',
            'target.email' => 'The target must be a valid email address.',
        ];
    }
}
