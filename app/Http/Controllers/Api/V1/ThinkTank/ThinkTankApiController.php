<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

abstract class ThinkTankApiController extends Controller
{
    /**
     * Validate an exact transport contract and reject undeclared fields.
     * Safe reads accept query parameters only; mutations accept a JSON object
     * only and reject every query parameter so credentials never belong in a
     * URL, referrer, access log, or audit record.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     */
    protected function validateOnly(Request $request, array $rules, array $messages = []): array
    {
        $input = $request->isMethodSafe()
            ? $request->query->all()
            : $this->mutationJson($request);
        $unexpected = array_diff(array_keys($input), array_keys($rules));

        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $field): array => [$field => ['This field is not allowed.']])
                ->all());
        }

        return Validator::make($input, $rules, $messages)->validate();
    }

    /** @return array<string, mixed> */
    private function mutationJson(Request $request): array
    {
        if ($request->query->count() !== 0) {
            throw ValidationException::withMessages([
                'query' => ['Query parameters are not allowed on API mutations. Send fields in the JSON body.'],
            ]);
        }

        if (! $request->isJson()) {
            throw new ThinkTankApiException(
                'JSON_BODY_REQUIRED',
                'API mutations require an application/json request body.',
                415,
            );
        }

        $content = trim($request->getContent());

        if ($content === '') {
            throw new ThinkTankApiException(
                'JSON_BODY_REQUIRED',
                'API mutations require a JSON object request body.',
                415,
            );
        }

        try {
            $document = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
            $input = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ThinkTankApiException(
                'INVALID_JSON',
                'The request body must contain valid JSON.',
                400,
            );
        }

        if (! is_object($document) || ! is_array($input)) {
            throw new ThinkTankApiException(
                'JSON_OBJECT_REQUIRED',
                'The request body must be a JSON object.',
                400,
            );
        }

        return $input;
    }
}
