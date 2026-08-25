<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiSyncAbandonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Keep the recovery credential out of the mutable request input bag.
     *
     * @return array<string, string|null>
     */
    public function validationData(): array
    {
        return [
            'recovery_key' => trim((string) $this->header('X-Claim-Recovery-Key')),
            'consumer_instance' => trim((string) $this->header('X-Consumer-Instance')),
            'idempotency_key' => trim((string) $this->header('Idempotency-Key')) ?: null,
        ];
    }

    public function rules(): array
    {
        return [
            'recovery_key' => ['bail', 'required', 'string', 'min:43', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'consumer_instance' => ['bail', 'required', 'string', 'min:8', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'recovery_key.required' => 'The X-Claim-Recovery-Key header is required.',
            'recovery_key.min' => 'The claim recovery key must be a high-entropy base64url value.',
            'recovery_key.regex' => 'The claim recovery key must be an unpadded base64url value.',
            'consumer_instance.required' => 'The X-Consumer-Instance header is required.',
            'idempotency_key.uuid' => 'The optional Idempotency-Key header must be a UUID.',
        ];
    }
}
