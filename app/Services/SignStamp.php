<?php

namespace App\Services;

/**
 * "IMZOLANDI dd.mm.yyyy" — rezina muhrga o'xshash belgi (PNG, GD bilan): ko'k, qo'sh ramka, biroz qiya,
 * fon shaffof. Qaydnomada tasdiqlagan har bir o'qituvchi, kafedra mudiri va dekan ismidan keyin.
 */
class SignStamp
{
    public static function png(string $date): string
    {
        $font = resource_path('fonts/liberation/LiberationSerif-Bold.ttf');
        $text = 'IMZOLANDI  ' . $date;
        $size = 52;
        $box = imagettfbbox($size, 0, $font, $text);
        $tw = $box[2] - $box[0];
        $th = $box[1] - $box[7];
        $h = 100;
        $w = $tw + 56;

        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagealphablending($img, true);
        $ink = imagecolorallocatealpha($img, 29, 64, 175, 10);

        // Qo'sh ramka: tashqi qalin, ichki ingichka (muhr chetidagidek)
        $frame = function (int $inset, int $thick) use ($img, $w, $h, $ink) {
            for ($i = 0; $i < $thick; $i++) {
                imagerectangle($img, $inset + $i, $inset + $i, $w - 1 - $inset - $i, $h - 1 - $inset - $i, $ink);
            }
        };
        $frame(2, 5);
        $frame(11, 2);
        imagettftext($img, $size, 0, (int) (($w - $tw) / 2) - $box[0], (int) (($h + $th) / 2) - 4, $ink, $font, $text);

        // Biroz qiya — qo'lda bosilgan muhrdek
        $rotated = imagerotate($img, 1, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagesavealpha($rotated, true);
        imagedestroy($img);

        ob_start();
        imagepng($rotated);
        imagedestroy($rotated);

        return (string) ob_get_clean();
    }

    /** Kenglik / balandlik nisbati (sana uzunligi doim bir xil — nisbat ham) */
    public static function aspect(): float
    {
        static $aspect = null;
        if ($aspect === null) {
            [$w, $h] = getimagesizefromstring(self::png('00.00.0000'));
            $aspect = $w / max($h, 1);
        }

        return $aspect;
    }
}
