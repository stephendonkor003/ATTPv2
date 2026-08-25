<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiSyncClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Keep header credentials out of the mutable request input bag while still
     * providing a narrowly scoped, normalized validation payload.
     *
     * @return array<string, string>
     */
    public function validationData(): array
    {
        return [
            'idempotency_key' => trim((string) $this->header('Idempotency-Key')),
            'recovery_key' => trim((string) $this->header('X-Claim-Recovery-Key')),
            'code' => trim((string) $this->input('code')),
            'consumer_instance' => trim((string) $this->input('consumer_instance')),
            'consumer_name' => trim((string) $this->input('consumer_name')),
        ];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{7}$/'],
            'consumer_instance' => ['required', 'string', 'min:8', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'consumer_name' => ['required', 'string', 'min:3', 'max:160'],
            'idempotency_key' => ['required', 'uuid'],
            'recovery_key' => ['bail', 'required', 'string', 'min:43', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The pairing code must contain exactly seven digits.',
            'idempotency_key.required' => 'The Idempotency-Key header is required.',
            'idempotency_key.uuid' => 'The Idempotency-Key header must be a UUID.',
            'recovery_key.required' => 'The X-Claim-Recovery-Key header is required.',
            'recovery_key.min' => 'The claim recovery key must be a high-entropy base64url value.',
            'recovery_key.regex' => 'The claim recovery key must be an unpadded base64url value.',
        ];
    }
}
