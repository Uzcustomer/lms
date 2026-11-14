<?php

namespace App\Services;

/**
 * YN qaydnoma shakllari — har biri alohida vedomost (o'z tasdiq zanjiri, Excel / PDF va QR i bilan):
 * 12 (asosiy), 12-qo'shimcha (sababli), 12a / 12b (qayta topshirish) va ularning qo'shimchalari.
 * Kod — bazada va havolalarda; YnAttemptStatusService shakli — qaydnoma varag'ini tanlash uchun.
 */
class YnqForm
{
    public const MAIN = '12';

    private const FORMS = [
        '12' => [YnAttemptStatusService::FORM_12, '12-shakl'],
        '12q' => [YnAttemptStatusService::FORM_12_QOSHIMCHA, "12-shakl qo'shimchasi"],
        '12a' => [YnAttemptStatusService::FORM_12A, '12a-shakl'],
        '12aq' => [YnAttemptStatusService::FORM_12A_QOSHIMCHA, "12a-shakl qo'shimchasi"],
        '12b' => [YnAttemptStatusService::FORM_12B, '12b-shakl'],
        '12bq' => [YnAttemptStatusService::FORM_12B_QOSHIMCHA, "12b-shakl qo'shimchasi"],
    ];

    /** @return string[] barcha kodlar, tartib bilan */
    public static function codes(): array
    {
        // '12' kaliti PHP da butun songa aylanadi — hammasi satr
        return array_map('strval', array_keys(self::FORMS));
    }

    /** Noma'lum yoki bo'sh — asosiy (12) */
    public static function normalize(?string $code): string
    {
        return isset(self::FORMS[(string) $code]) ? (string) $code : self::MAIN;
    }

    public static function label(?string $code): string
    {
        return self::FORMS[self::normalize($code)][1];
    }

    /** YnAttemptStatusService shakli (qaydnoma varag'i) */
    public static function sheetForm(?string $code): string
    {
        return self::FORMS[self::normalize($code)][0];
    }

    public static function fromSheetForm(string $sheetForm): ?string
    {
        foreach (self::FORMS as $code => [$form]) {
            if ($form === $sheetForm) {
                return (string) $code;
            }
        }

        return null;
    }
}
