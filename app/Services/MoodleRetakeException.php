<?php

namespace App\Services;

use RuntimeException;
use Throwable;

/** Moodle otrabotka web-servisiga ulanib bo'lmadi yoki u xato qaytardi */
class MoodleRetakeException extends RuntimeException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null, public readonly string $errorCode = '')
    {
        parent::__construct($message, $code, $previous);
    }
}
