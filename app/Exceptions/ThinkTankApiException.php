<?php

namespace App\Exceptions;

use RuntimeException;

class ThinkTankApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $data = [],
    ) {
        parent::__construct($message);
    }
}
