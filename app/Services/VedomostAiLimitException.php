<?php

namespace App\Services;

use RuntimeException;

/**
 * AI limiti tugadi (kunlik umumiy limit yoki barcha kalitlarning limiti).
 * Job bunday holatda vedomostni xato deb emas, "keyinroq tekshiriladi" deb belgilaydi.
 */
class VedomostAiLimitException extends RuntimeException
{
}
