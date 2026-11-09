<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $statusCode = 400,
        ?string $message = null,
        ?\Throwable $previous = null
    ) {
        $resolvedMessage = $message ?? (__("messages.{$errorCode}") !== "messages.{$errorCode}"
            ? __("messages.{$errorCode}")
            : 'An error occurred.');

        parent::__construct($resolvedMessage, $statusCode, $previous);
    }
}
