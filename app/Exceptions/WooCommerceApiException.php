<?php

namespace App\Exceptions;

use Exception;

class WooCommerceApiException extends Exception
{
    public function __construct(string $message, int $status, public readonly ?string $errorCode = null)
    {
        parent::__construct($message, $status);
    }
}
