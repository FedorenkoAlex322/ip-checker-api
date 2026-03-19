<?php

namespace App\Http\Requests;

final class DomainLookupRequest extends ApiFormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'target' => [
                'required',
                'string',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'target.required' => 'The domain name is required.',
            'target.regex' => 'The target must be a valid domain name (e.g. example.com).',
        ];
    }
}
