<?php

namespace App\Exceptions;

use RuntimeException;

/** A user-facing attendance failure; the message is safe to show as-is. */
class AttendanceException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
