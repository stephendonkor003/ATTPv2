<?php

namespace App\Exceptions;

use RuntimeException;

class ApiSyncDocumentHeldException extends RuntimeException
{
    public function __construct(
        public readonly string $holdCode,
        string $safeMessage,
    ) {
        parent::__construct($safeMessage);
    }
}
