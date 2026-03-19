<?php

namespace App\Http\Requests;

final class IpLookupRequest extends ApiFormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'ip'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'target.required' => 'The IP address is required.',
            'target.ip' => 'The target must be a valid IPv4 or IPv6 address.',
        ];
    }
}
