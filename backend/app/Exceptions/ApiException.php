<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $statusCode = 400,
        ?string $message = null,
        public readonly array $details = [],
        ?\Throwable $previous = null
    ) {
        $resolvedMessage = $message ?? (__("messages.{$errorCode}") !== "messages.{$errorCode}"
            ? __("messages.{$errorCode}")
            : 'An error occurred.');

        parent::__construct($resolvedMessage, $statusCode, $previous);
    }
}
