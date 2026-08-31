<?php

namespace App\Exceptions;

use RuntimeException;

final class TransientThreepapScreeningException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A transient 3PAP screening failure will be retried.');
    }
}
