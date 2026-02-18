<?php

namespace App\Services;

class TableImageGenerator
{
    private string $fontPath = '';
    private string $boldFontPath = '';
    private int $fontSize = 9;
    private int $headerFontSize = 10;
    private int $titleFontSize = 13;
    private int $cellPadding = 6;
    private int $rowHeight = 24;
    private int $headerHeight = 28;
    private int $titleHeight = 36;
    private int $maxRowsPerImage = 35;

    public function __construct()
    {
        $fontPaths = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ];

        foreach ($fontPaths as $path) {
            if (file_exists($path)) {
                $this->fontPath = $path;
                break;
            }
        }

        $boldFontPaths = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        ];

        foreach ($boldFontPaths as $path) {
            if (file_exists($path)) {
                $this->boldFontPath = $path;
                break;
            }
        }

        if (!$this->boldFontPath) {
            $this->boldFontPath = $this->fontPath;
        }
    }

    /**
     * Generate table image(s). Returns array of temporary file paths.
     */
    public function generate(array $headers, array $rows, string $title = ''): array
    {
        if (empty($rows)) {
            return [];
        }

        $images = [];
        $chunks = array_chunk($rows, $this->maxRowsPerImage);

        foreach ($chunks as $index => $chunk) {
            $pageTitle = $title;
            if (count($chunks) > 1) {
                $pageTitle .= ' (' . ($index + 1) . '/' . count($chunks) . ')';
            }
            $images[] = $this->generateSingleImage($headers, $chunk, $pageTitle);
        }

        return $images;
    }

    private function generateSingleImage(array $headers, array $rows, string $title): string
    {
        // Calculate column widths based on content
        $colWidths = [];
        foreach ($headers as $i => $header) {
            $colWidths[$i] = $this->getTextWidth($header, $this->headerFontSize) + $this->cellPadding * 2;
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $cellStr = $this->displayValue($cell);
                $width = $this->getTextWidth($cellStr, $this->fontSize) + $this->cellPadding * 2;
                if ($width > ($colWidths[$i] ?? 0)) {
                    $colWidths[$i] = $width;
                }
            }
        }

        // Minimum column widths
        foreach ($colWidths as $i => $w) {
            $colWidths[$i] = max($w, 28);
        }

        $totalWidth = array_sum($colWidths) + count($colWidths) + 1;
        $hasTitleRow = !empty($title);
        $totalHeight = ($hasTitleRow ? $this->titleHeight : 0) + $this->headerHeight + count($rows) * $this->rowHeight + 1;

        $image = imagecreatetruecolor($totalWidth, $totalHeight);

        // Colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 33, 37, 41);
        $headerBg = imagecolorallocate($image, 52, 58, 64);
        $headerTextColor = imagecolorallocate($image, 255, 255, 255);
        $evenRowBg = imagecolorallocate($image, 255, 255, 255);
        $oddRowBg = imagecolorallocate($image, 248, 249, 250);
        $borderColor = imagecolorallocate($image, 222, 226, 230);
        $redRowBg = imagecolorallocate($image, 255, 235, 238);
        $greenText = imagecolorallocate($image, 21, 87, 36);
        $redText = imagecolorallocate($image, 183, 28, 28);
        $titleBg = imagecolorallocate($image, 25, 118, 210);
        $titleTextColor = imagecolorallocate($image, 255, 255, 255);
        $grayText = imagecolorallocate($image, 150, 150, 150);
        $headerBorderColor = imagecolorallocate($image, 73, 80, 87);

        // Fill background
        imagefilledrectangle($image, 0, 0, $totalWidth - 1, $totalHeight - 1, $white);

        $y = 0;

        // Title row
        if ($hasTitleRow) {
            imagefilledrectangle($image, 0, 0, $totalWidth - 1, $this->titleHeight - 1, $titleBg);
            $this->drawText($image, $title, 10, ($this->titleHeight - $this->titleFontSize) / 2, $titleTextColor, $this->titleFontSize, true);
            $y = $this->titleHeight;
        }

        // Header row
        imagefilledrectangle($image, 0, $y, $totalWidth - 1, $y + $this->headerHeight - 1, $headerBg);
        $x = 0;
        foreach ($headers as $i => $header) {
            $this->drawText($image, $header, $x + $this->cellPadding + 1, $y + ($this->headerHeight - $this->headerFontSize) / 2, $headerTextColor, $this->headerFontSize, true);
            $x += $colWidths[$i] + 1;
            // Vertical separator in header
            imageline($image, $x - 1, $y, $x - 1, $y + $this->headerHeight - 1, $headerBorderColor);
        }

        $y += $this->headerHeight;

        // Data rows
        foreach ($rows as $rowIndex => $row) {
            // Determine row background
            $hasIssue = false;
            foreach ($row as $cell) {
                if ($cell === false) {
                    $hasIssue = true;
                    break;
                }
            }

            if ($hasIssue) {
                $bgColor = $redRowBg;
            } else {
                $bgColor = ($rowIndex % 2 === 0) ? $evenRowBg : $oddRowBg;
            }

            imagefilledrectangle($image, 0, $y, $totalWidth - 1, $y + $this->rowHeight - 1, $bgColor);
            // Top border of row
            imageline($image, 0, $y, $totalWidth - 1, $y, $borderColor);

            $x = 0;
            foreach ($row as $i => $cell) {
                $cellStr = $this->displayValue($cell);

                // Pick text color based on value
                $textColor = $black;
                if ($cell === true) {
                    $textColor = $greenText;
                } elseif ($cell === false) {
                    $textColor = $redText;
                } elseif ($cell === '-' || $cell === null) {
                    $textColor = $grayText;
                }

                $this->drawText($image, $cellStr, $x + $this->cellPadding + 1, $y + ($this->rowHeight - $this->fontSize) / 2, $textColor, $this->fontSize);

                $x += $colWidths[$i] + 1;
                // Vertical separator
                imageline($image, $x - 1, $y, $x - 1, $y + $this->rowHeight - 1, $borderColor);
            }

            $y += $this->rowHeight;
        }

        // Bottom border
        imageline($image, 0, $y, $totalWidth - 1, $y, $borderColor);
        // Left border
        imageline($image, 0, 0, 0, $totalHeight - 1, $borderColor);
        // Right border
        imageline($image, $totalWidth - 1, 0, $totalWidth - 1, $totalHeight - 1, $borderColor);

        // Save to system temp directory (always writable)
        $tempPath = sys_get_temp_dir() . '/telegram_table_' . uniqid() . '.png';

        imagepng($image, $tempPath, 5);
        imagedestroy($image);

        return $tempPath;
    }

    /**
     * Convert cell value to display string.
     * true = "Ha" (✅), false = "Yo'q" (❌), null/dash = "-"
     */
    private function displayValue($cell): string
    {
        if ($cell === true) {
            return 'Ha';
        }
        if ($cell === false) {
            return "Yo'q";
        }
        if ($cell === null) {
            return '-';
        }
        return (string) $cell;
    }

    private function getTextWidth(string $text, int $fontSize): int
    {
        if ($this->fontPath && function_exists('imagettfbbox')) {
            $bbox = @imagettfbbox($fontSize, 0, $this->fontPath, $text);
            if ($bbox !== false) {
                return abs($bbox[2] - $bbox[0]);
            }
        }
        return mb_strlen($text) * ($fontSize <= 9 ? 6 : 7);
    }

    private function drawText($image, string $text, int $x, int $y, $color, int $fontSize, bool $bold = false): void
    {
        $font = $bold ? $this->boldFontPath : $this->fontPath;

        if ($font && function_exists('imagettftext')) {
            // TTF y-coordinate is baseline, so offset by font size
            @imagettftext($image, $fontSize, 0, $x, (int) ($y + $fontSize + 2), $color, $font, $text);
        } else {
            // Fallback to GD built-in fonts
            $gdFont = ($fontSize <= 9) ? 2 : 3;
            imagestring($image, $gdFont, $x, (int) $y, $text, $color);
        }
    }

    /**
     * Truncate text to max length.
     */
    public static function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        return mb_substr($text, 0, $maxLength - 2) . '..';
    }
}
