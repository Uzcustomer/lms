<?php

namespace App\Services;

/**
 * Vedomost (YN qaydnoma) baho kataklarini hisoblaydi — YnQaytnomaController::
 * generateYnQaydnoma() bilan AYNAN bir xil formula va yaxlitlash.
 *
 * Og'irliklar (JB/MT/ON/OSKI/Test maksimal bali) PARAMETR sifatida beriladi —
 * ular har vedomostda PDF sarlavhasida ko'rsatiladi va AI skanerdan o'qib oladi.
 * Shu bilan hisob PHP'da (determinik), AI esa faqat skanerni o'qiydi.
 */
class VedomostGradeCalculator
{
    /**
     * Bitta talaba uchun barcha katak qiymatlarini hisoblaydi.
     *
     * @param  array  $w  og'irliklar: ['jn'=>50,'mt'=>20,'on'=>0,'oski'=>0,'test'=>30]
     * @param  string  $levelCode  semestr level_code (14/15 = 4-5 kurs → ball butun)
     * @return array{jb_ball:int|float,mt_ball:int|float,on_ball:int|float,
     *               oski_ball:int|float,test_ball:int|float,jbmton_ball:int|float,
     *               ozlashtirish:?int,ects:string,baho:string}
     */
    public function compute($jn, $mt, $on, $oski, $test, ?string $levelCode, array $w, float $davomatPct = 0.0): array
    {
        $wJn = (int) ($w['jn'] ?? 0);
        $wMt = (int) ($w['mt'] ?? 0);
        $wOn = (int) ($w['on'] ?? 0);
        $wOski = (int) ($w['oski'] ?? 0);
        $wTest = (int) ($w['test'] ?? 0);

        $jnVal   = ($jn   !== '' && $jn   !== null) ? (int) $jn                  : null;
        $mtVal   = ($mt   !== '' && $mt   !== null) ? (int) $mt                  : null;
        $onVal   = ($on   !== '' && $on   !== null) ? (int) round((float) $on)   : null;
        $oskiVal = ($oski !== '' && $oski !== null) ? (int) round((float) $oski) : null;
        $testVal = ($test !== '' && $test !== null) ? (int) round((float) $test) : null;

        // JB/MT/ON ball — 4-5 kurs (14/15) butun (half-up), aks holda 1 kasr.
        $roundToInt = in_array((string) $levelCode, ['14', '15'], true);
        if ($roundToInt) {
            $eBall = ($jnVal !== null && $jnVal >= 60) ? (int) floor($jnVal * $wJn / 100 + 0.5) : 0;
            $hBall = ($mtVal !== null && $mtVal >= 60) ? (int) floor($mtVal * $wMt / 100 + 0.5) : 0;
            $kBall = ($onVal !== null && $onVal >= 60) ? (int) floor($onVal * $wOn / 100 + 0.5) : 0;
        } else {
            $eBall = ($jnVal !== null && $jnVal >= 60) ? round($jnVal * $wJn / 100, 1) : 0;
            $hBall = ($mtVal !== null && $mtVal >= 60) ? round($mtVal * $wMt / 100, 1) : 0;
            $kBall = ($onVal !== null && $onVal >= 60) ? round($onVal * $wOn / 100, 1) : 0;
        }

        // OSKI/Test ball — ikkalasi vaznli bo'lsa 1 kasr, bittasi bo'lsa butun.
        if ($wOski > 0 && $wTest > 0) {
            $qBall = ($oskiVal !== null && $oskiVal >= 60) ? $oskiVal * $wOski / 100 : 0;
            $tBall = ($testVal !== null && $testVal >= 60) ? $testVal * $wTest / 100 : 0;
        } elseif ($wOski > 0) {
            $qBall = ($oskiVal !== null && $oskiVal >= 60) ? (int) round($oskiVal * $wOski / 100) : 0;
            $tBall = 0;
        } elseif ($wTest > 0) {
            $qBall = 0;
            $tBall = ($testVal !== null && $testVal >= 60) ? (int) round($testVal * $wTest / 100) : 0;
        } else {
            $qBall = 0;
            $tBall = 0;
        }

        $maxJbMtOn = $wJn + $wMt + $wOn;
        $mSum = (($jnVal !== null && $jnVal < 60) || ($mtVal !== null && $mtVal < 60))
            ? 0
            : round($eBall + $hBall + $kBall, 1);
        $nPct = $maxJbMtOn > 0 ? $mSum / $maxJbMtOn : 0;

        $davomatFailed = $davomatPct >= 25;
        if ($jnVal === null && $mtVal === null) {
            $v = '';
        } elseif ($davomatFailed) {
            $v = -3;
        } elseif ($nPct < 0.6) {
            $v = -2;
        } elseif (($wOski > 0 && ($oskiVal === null || $oskiVal == 0))
               || ($wTest > 0 && ($testVal === null || $testVal == 0))) {
            $v = -1;
        } elseif (($wJn > 0   && $jnVal   !== null && $jnVal   < 60)
               || ($wMt > 0   && $mtVal   !== null && $mtVal   < 60)
               || ($wOn > 0   && $onVal   !== null && $onVal   < 60)
               || ($wOski > 0 && $oskiVal !== null && $oskiVal < 60)
               || ($wTest > 0 && $testVal !== null && $testVal < 60)) {
            $v = 0;
        } else {
            $jbMtOnSum = round($eBall + $hBall + $kBall, 1);
            if ($wOski > 0 && $wTest > 0) {
                $examSum = round($qBall + $tBall, 1);
            } elseif ($wOski > 0) {
                $examSum = round($qBall, 1);
            } elseif ($wTest > 0) {
                $examSum = round($tBall, 1);
            } else {
                $examSum = 0;
            }
            $v = $jbMtOnSum + $examSum;
        }
        if (is_numeric($v) && $v > 0) {
            $v = (int) floor((float) $v + 0.5);
        }

        return [
            'jb_ball' => $eBall,
            'mt_ball' => $hBall,
            'on_ball' => $kBall,
            'oski_ball' => $qBall,
            'test_ball' => $tBall,
            'jbmton_ball' => $mSum,
            'ozlashtirish' => is_numeric($v) ? $v : null,
            'ects' => $this->toEcts($v),
            'baho' => $this->toBaho($v),
        ];
    }

    private function toEcts($v): string
    {
        if (!is_numeric($v)) {
            return '';
        }
        if ($v >= 90 && $v <= 100) return 'A';
        if ($v >= 85) return 'B+';
        if ($v >= 70) return 'B';
        if ($v >= 60) return 'C';
        if ($v >= 0 && $v <= 60) return 'F';
        if ($v == -1 || $v == -3) return '';

        return 'FX';
    }

    private function toBaho($v): string
    {
        if (!is_numeric($v)) {
            return '';
        }
        if ($v >= 90 && $v <= 100) return "a'lo";
        if ($v >= 70 && $v <= 89.9) return 'yaxshi';
        if ($v >= 60 && $v <= 69.9) return "o'rta";
        if ($v >= 0 && $v <= 59.9) return 'qoniqarsiz';
        if ($v == -1) return 'kelmadi';
        if ($v == -2) return "qo'yilmadi";
        if ($v == -3) return "davomat ≥25%";

        return '';
    }
}
