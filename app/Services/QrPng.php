<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * QR kodni PNG ga chizadi (GD bilan). simple-qrcode PNG uchun imagick talab qiladi, serverda
 * faqat GD bor — shuning uchun BaconQrCode Encoder matritsasi bevosita chiziladi.
 */
class QrPng
{
    /** @param array{0:int,1:int,2:int} $color QR nuqtalari rangi (RGB), sukut — qora */
    public static function make(string $text, int $scale = 6, int $margin = 2, array $color = [0, 0, 0]): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $size = $matrix->getWidth();
        $px = ($size + 2 * $margin) * $scale;

        $img = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, ...$color);
        imagefill($img, 0, 0, $white);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $x0 = ($x + $margin) * $scale;
                    $y0 = ($y + $margin) * $scale;
                    imagefilledrectangle($img, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
