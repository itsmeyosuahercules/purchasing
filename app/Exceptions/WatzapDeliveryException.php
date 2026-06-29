<?php

namespace App\Exceptions;

use RuntimeException;

class WatzapDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message);
    }
}
