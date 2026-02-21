<?php

namespace App\Services;

class TableImageGenerator
{
    private string $fontPath = '';
    private string $boldFontPath = '';
    private int $fontSize = 10;
    private int $headerFontSize = 10;
    private int $titleFontSize = 14;
    private int $cellPadding = 8;
    private int $rowHeight = 28;
    private int $headerHeight = 32;
    private int $titleHeight = 42;
    private int $maxRowsPerImage = 35;
    private bool $compactMode = false;

    /**
     * Ixcham rejimni yoqish (kichik shrift, past qator balandligi, ko'proq qator).
     */
    public function compact(): self
    {
        $this->compactMode = true;
        $this->fontSize = 8;
        $this->headerFontSize = 8;
        $this->titleFontSize = 11;
        $this->cellPadding = 4;
        $this->rowHeight = 20;
        $this->headerHeight = 24;
        $this->titleHeight = 30;
        $this->maxRowsPerImage = 120;

        return $this;
    }

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
        $accentBarWidth = $this->compactMode ? 3 : 4;

        // Calculate column widths based on content
        $colWidths = [];
        foreach ($headers as $i => $header) {
            $colWidths[$i] = $this->getTextWidth($header, $this->headerFontSize) + $this->cellPadding * 2;
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $cellStr = $this->displayValue($cell);
                $width = $this->getTextWidth($cellStr, $this->fontSize) + $this->cellPadding * 2;
                // Ha/Yo'q uchun badge padding qo'shish
                if ($cell === true || $cell === false) {
                    $width += $this->compactMode ? 8 : 12;
                }
                if ($width > ($colWidths[$i] ?? 0)) {
                    $colWidths[$i] = $width;
                }
            }
        }

        // Minimum column widths
        foreach ($colWidths as $i => $w) {
            $colWidths[$i] = max($w, $this->compactMode ? 28 : 36);
        }

        $totalWidth = array_sum($colWidths) + $accentBarWidth;
        $hasTitleRow = !empty($title);
        $totalHeight = ($hasTitleRow ? $this->titleHeight : 0) + $this->headerHeight + count($rows) * $this->rowHeight + 2;

        $image = imagecreatetruecolor($totalWidth, $totalHeight);
        imagesavealpha($image, true);

        // === Zamonaviy rang palitrasi ===
        $white = imagecolorallocate($image, 255, 255, 255);
        $textPrimary = imagecolorallocate($image, 30, 41, 59);         // Asosiy matn — quyuq ko'k-kulrang
        $textSecondary = imagecolorallocate($image, 100, 116, 139);    // Ikkinchi darajali matn

        // Sarlavha — gradient effektli chuqur ko'k
        $titleBg = imagecolorallocate($image, 15, 23, 42);
        $titleBgLight = imagecolorallocate($image, 26, 42, 74);
        $titleTextColor = imagecolorallocate($image, 255, 255, 255);
        $titleAccent = imagecolorallocate($image, 99, 179, 237);       // Ochiq ko'k ta'kid

        // Header — quyuq kulrang-ko'k
        $headerBg = imagecolorallocate($image, 34, 49, 74);
        $headerTextColor = imagecolorallocate($image, 200, 215, 235);

        // Qatorlar
        $evenRowBg = imagecolorallocate($image, 255, 255, 255);
        $oddRowBg = imagecolorallocate($image, 245, 247, 251);
        $borderColor = imagecolorallocate($image, 226, 232, 240);
        $borderLight = imagecolorallocate($image, 237, 242, 247);

        // Muammoli qatorlar — yumshoq qizil
        $redRowBg = imagecolorallocate($image, 254, 242, 242);
        $redAccent = imagecolorallocate($image, 220, 38, 38);

        // Ha/Yo'q badge ranglari
        $greenBadgeBg = imagecolorallocate($image, 220, 252, 231);
        $greenBadgeText = imagecolorallocate($image, 22, 101, 52);
        $redBadgeBg = imagecolorallocate($image, 254, 226, 226);
        $redBadgeText = imagecolorallocate($image, 185, 28, 28);

        // Guruh sarlavha qatorlari
        $groupHeaderBg = imagecolorallocate($image, 224, 231, 243);
        $groupHeaderText = imagecolorallocate($image, 30, 58, 95);

        // Chap accent bari
        $accentDefault = imagecolorallocate($image, 59, 130, 246);     // Ko'k
        $accentGreen = imagecolorallocate($image, 34, 197, 94);        // Yashil — muammosiz

        // Fon
        imagefilledrectangle($image, 0, 0, $totalWidth - 1, $totalHeight - 1, $white);

        $y = 0;

        // === SARLAVHA ===
        if ($hasTitleRow) {
            // Gradient effekt — 2 rangdan
            $halfH = (int) ($this->titleHeight / 2);
            imagefilledrectangle($image, 0, 0, $totalWidth - 1, $halfH, $titleBg);
            imagefilledrectangle($image, 0, $halfH, $totalWidth - 1, $this->titleHeight - 1, $titleBgLight);

            // Chap tomonda vertikal ta'kid chizig'i
            imagefilledrectangle($image, 0, 0, $accentBarWidth - 1, $this->titleHeight - 1, $titleAccent);

            // Sarlavha matni
            $this->drawText($image, $title, $accentBarWidth + 10, ($this->titleHeight - $this->titleFontSize) / 2, $titleTextColor, $this->titleFontSize, true);

            $y = $this->titleHeight;
        }

        // === HEADER ===
        imagefilledrectangle($image, 0, $y, $totalWidth - 1, $y + $this->headerHeight - 1, $headerBg);
        // Chap accent
        imagefilledrectangle($image, 0, $y, $accentBarWidth - 1, $y + $this->headerHeight - 1, $accentDefault);

        $x = $accentBarWidth;
        foreach ($headers as $i => $header) {
            $this->drawText(
                $image,
                mb_strtoupper($header),
                $x + $this->cellPadding,
                $y + ($this->headerHeight - $this->headerFontSize) / 2,
                $headerTextColor,
                $this->headerFontSize,
                true
            );
            $x += $colWidths[$i];
        }

        // Header pastki chizig'i
        $y += $this->headerHeight;
        imagefilledrectangle($image, 0, $y, $totalWidth - 1, $y + 1, $accentDefault);
        $y += 2;

        // === MA'LUMOT QATORLARI ===
        foreach ($rows as $rowIndex => $row) {
            // Muammoli qatorni aniqlash
            $hasIssue = false;
            foreach ($row as $cell) {
                if ($cell === false) {
                    $hasIssue = true;
                    break;
                }
            }

            // Guruh sarlavha qatorini aniqlash
            $isGroupHeader = isset($row[0]) && is_int($row[0]) && $row[0] > 0
                && isset($row[1]) && is_string($row[1]) && !str_starts_with($row[1], '   ');
            $hasChildRow = isset($rows[$rowIndex + 1][0]) && $rows[$rowIndex + 1][0] === '';
            $isGroupHeaderRow = $isGroupHeader && $hasChildRow;

            // Fon rangi
            if ($isGroupHeaderRow) {
                $bgColor = $groupHeaderBg;
            } elseif ($hasIssue) {
                $bgColor = $redRowBg;
            } else {
                $bgColor = ($rowIndex % 2 === 0) ? $evenRowBg : $oddRowBg;
            }

            imagefilledrectangle($image, 0, $y, $totalWidth - 1, $y + $this->rowHeight - 1, $bgColor);

            // Chap accent bar
            if ($hasIssue) {
                imagefilledrectangle($image, 0, $y, $accentBarWidth - 1, $y + $this->rowHeight - 1, $redAccent);
            } elseif ($isGroupHeaderRow) {
                imagefilledrectangle($image, 0, $y, $accentBarWidth - 1, $y + $this->rowHeight - 1, $accentDefault);
            } else {
                imagefilledrectangle($image, 0, $y, $accentBarWidth - 1, $y + $this->rowHeight - 1, $borderLight);
            }

            // Pastki ajratuvchi chiziq
            imageline($image, $accentBarWidth, $y + $this->rowHeight - 1, $totalWidth - 1, $y + $this->rowHeight - 1, $borderColor);

            // Hujayralar
            $x = $accentBarWidth;
            foreach ($row as $i => $cell) {
                $cellStr = $this->displayValue($cell);

                // Ha/Yo'q uchun badge chizish
                if ($cell === true || $cell === false) {
                    $this->drawBadge(
                        $image, $cellStr,
                        $x + $this->cellPadding,
                        $y,
                        $this->rowHeight,
                        $cell === true ? $greenBadgeBg : $redBadgeBg,
                        $cell === true ? $greenBadgeText : $redBadgeText
                    );
                } else {
                    // Oddiy matn
                    $textColor = $isGroupHeaderRow ? $groupHeaderText : $textPrimary;
                    if ($cell === '-' || $cell === null) {
                        $textColor = $textSecondary;
                    }

                    $useBold = $isGroupHeaderRow;
                    $this->drawText(
                        $image, $cellStr,
                        $x + $this->cellPadding,
                        $y + ($this->rowHeight - $this->fontSize) / 2,
                        $textColor, $this->fontSize, $useBold
                    );
                }

                $x += $colWidths[$i];

                // Vertikal ajratuvchi (ingichka, yumshoq)
                if ($i < count($row) - 1) {
                    imageline($image, $x, $y + 4, $x, $y + $this->rowHeight - 5, $borderLight);
                }
            }

            $y += $this->rowHeight;
        }

        // Pastki chegara
        imagefilledrectangle($image, 0, $y, $totalWidth - 1, $y + 1, $borderColor);

        // Saqlash
        $tempPath = sys_get_temp_dir() . '/telegram_table_' . uniqid() . '.png';
        imagepng($image, $tempPath, 5);
        imagedestroy($image);

        return $tempPath;
    }

    /**
     * Ha/Yo'q uchun rangli badge chizish
     */
    private function drawBadge($image, string $text, int $x, int $rowY, int $rowHeight, $bgColor, $textColor): void
    {
        $textWidth = $this->getTextWidth($text, $this->fontSize);
        $badgePadH = $this->compactMode ? 3 : 5;
        $badgePadV = $this->compactMode ? 2 : 3;
        $badgeW = $textWidth + $badgePadH * 2;
        $badgeH = $this->fontSize + $badgePadV * 2 + 2;
        $badgeY = $rowY + (int) (($rowHeight - $badgeH) / 2);

        // Badge foni
        imagefilledrectangle($image, $x, $badgeY, $x + $badgeW, $badgeY + $badgeH, $bgColor);

        // Badge matni
        $this->drawText($image, $text, $x + $badgePadH, $badgeY + $badgePadV - 1, $textColor, $this->fontSize, true);
    }

    /**
     * Convert cell value to display string.
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
            @imagettftext($image, $fontSize, 0, $x, (int) ($y + $fontSize + 2), $color, $font, $text);
        } else {
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
