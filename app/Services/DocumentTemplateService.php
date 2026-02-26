<?php

namespace App\Services;

use App\Models\AbsenceExcuse;
use App\Models\DocumentTemplate;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;

class DocumentTemplateService
{
    /**
     * Sababli ariza uchun Word shablonni to'ldirish va PDF ga aylantirish
     */
    public function generateAbsenceExcusePdf(AbsenceExcuse $excuse, string $reviewerName, ?string $qrImagePath = null): string
    {
        $template = DocumentTemplate::getActiveByType('absence_excuse');

        if (!$template) {
            throw new \RuntimeException('Faol shablon topilmadi. Admin paneldan "Sababli ariza farmoyishi" shablonini yuklang.');
        }

        $templatePath = Storage::disk('public')->path($template->file_path);

        if (!file_exists($templatePath)) {
            throw new \RuntimeException('Shablon fayli serverda topilmadi: ' . $template->file_original_name);
        }

        // O'zbek oy nomlari
        $months = [
            1 => 'yanvar', 2 => 'fevral', 3 => 'mart', 4 => 'aprel',
            5 => 'may', 6 => 'iyun', 7 => 'iyul', 8 => 'avgust',
            9 => 'sentabr', 10 => 'oktabr', 11 => 'noyabr', 12 => 'dekabr',
        ];

        $reviewDate = $excuse->reviewed_at ?? now();
        $year = now()->year;
        $month = now()->month;
        $academicYear = $month >= 9 ? $year . '.' . ($year + 1) : ($year - 1) . '.' . $year;

        // Yakshanbasiz kunlar soni
        $daysCount = 0;
        $d = $excuse->start_date->copy();
        while ($d->lte($excuse->end_date)) {
            if (!$d->isSunday()) $daysCount++;
            $d->addDay();
        }

        // PhpWord TemplateProcessor
        $processor = new TemplateProcessor($templatePath);

        // Word shablon ichida ${m_num} kabi makrolar bo'linib ketishi mumkin — tuzatish
        $this->fixBrokenTemplateMacros($processor);

        \Log::info('Template variables found: ' . implode(', ', $processor->getVariables()));

        // Matn placeholder'larni almashtirish
        $processor->setValue('student_name', $excuse->student_full_name);
        $processor->setValue('student_hemis_id', $excuse->student_hemis_id);
        $processor->setValue('group_name', $excuse->group_name ?? '');
        $processor->setValue('department_name', $excuse->department_name ?? '');
        $processor->setValue('reason', mb_strtolower($excuse->reason_label));
        $processor->setValue('reason_document', mb_strtolower($excuse->reason_document));
        $processor->setValue('start_date', $excuse->start_date->format('d.m.Y'));
        $processor->setValue('end_date', $excuse->end_date->format('d.m.Y'));
        $processor->setValue('days_count', (string) $daysCount);
        $processor->setValue('review_date', $reviewDate->format('d.m.Y'));
        $processor->setValue('review_date_full', $reviewDate->format('Y') . ' yil ' . $reviewDate->format('j') . '-' . ($months[$reviewDate->month] ?? $reviewDate->format('F')));
        $processor->setValue('reviewer_name', $reviewerName);
        $processor->setValue('order_number', '08-' . str_pad($excuse->id, 5, '0', STR_PAD_LEFT));
        $processor->setValue('academic_year', $academicYear);
        $processor->setValue('verification_url', route('absence-excuse.verify', $excuse->verification_token));

        // Nazoratlar jadvali (cloneRow orqali)
        $makeups = $excuse->makeups()->orderBy('subject_name')->get();
        $makeupCount = $makeups->count();

        if ($makeupCount > 0) {
            $typeLabels = [
                'jn' => 'Joriy nazorat',
                'mt' => 'Mustaqil ta\'lim',
                'oski' => 'YN (OSKE)',
                'test' => 'YN (Test)',
            ];

            try {
                $processor->cloneRow('m_num', $makeupCount);

                foreach ($makeups->values() as $i => $makeup) {
                    $idx = $i + 1;
                    $processor->setValue("m_num#{$idx}", (string) $idx);
                    $processor->setValue("m_subject#{$idx}", $makeup->subject_name ?? '');
                    $processor->setValue("m_type#{$idx}", $typeLabels[$makeup->assessment_type] ?? $makeup->assessment_type);
                    $processor->setValue("m_date#{$idx}", $this->formatMakeupDateRange($makeup));
                }
            } catch (\Throwable $e) {
                \Log::warning('cloneRow failed, using manual XML row cloning: ' . $e->getMessage());

                // cloneRow ishlamadi — Reflection orqali XML da qo'lda qatorlarni klonlash
                $this->manualCloneRow($processor, $makeups, $typeLabels);
            }
        } else {
            // Agar nazoratlar bo'lmasa placeholder'larni tozalash
            $processor->setValue('m_num', '');
            $processor->setValue('m_subject', '');
            $processor->setValue('m_type', '');
            $processor->setValue('m_date', '');
        }

        // QR kod rasm sifatida
        if ($qrImagePath && file_exists($qrImagePath)) {
            try {
                // Fayl haqiqiy rasm ekanligini tekshirish
                $imageInfo = @getimagesize($qrImagePath);
                if ($imageInfo !== false) {
                    $processor->setImageValue('qr_code', [
                        'path' => $qrImagePath,
                        'width' => 100,
                        'height' => 100,
                        'ratio' => true,
                    ]);
                } else {
                    // Fayl rasm emas (EPS yoki boshqa format) — placeholder ni o'chirish
                    $processor->setValue('qr_code', '');
                }
            } catch (\Throwable $e) {
                // setImageValue xatolik bersa — placeholder ni o'chirish
                $processor->setValue('qr_code', '');
            }
        } else {
            // QR kod yo'q bo'lsa, placeholder'ni o'chiramiz
            $processor->setValue('qr_code', '');
        }

        // To'ldirilgan .docx ni vaqtinchalik faylga saqlash
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempDocx = $tempDir . '/' . uniqid('template_') . '.docx';

        $processor->saveAs($tempDocx);

        // EMF rasmlarni PNG ga aylantirish (PhpWord/DomPDF EMF ni qo'llab-quvvatlamaydi)
        $this->convertEmfImagesToPng($tempDocx);

        $pdfPath = 'absence-excuses/approved/' . $excuse->verification_token . '.pdf';
        $pdfGenerated = false;

        // LibreOffice orqali PDF generatsiya (yagona sifatli usul)
        // HOME muhit o'zgaruvchisi kerak (www-data uchun)
        $command = sprintf(
            'HOME=/tmp /usr/bin/soffice --headless --norestore --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($tempDocx)
        );

        exec($command, $output, $returnCode);
        $outputStr = implode("\n", $output);

        \Log::info('LibreOffice command: ' . $command);
        \Log::info('LibreOffice output: ' . $outputStr);
        \Log::info('LibreOffice return code: ' . $returnCode);

        $generatedPdf = preg_replace('/\.docx$/', '.pdf', $tempDocx);

        if ($returnCode === 0 && file_exists($generatedPdf)) {
            Storage::disk('public')->put($pdfPath, file_get_contents($generatedPdf));
            @unlink($generatedPdf);
            $pdfGenerated = true;
        }

        // Tozalash
        @unlink($tempDocx);

        if (!$pdfGenerated) {
            $errorMsg = 'LibreOffice konvertatsiya xatosi (code: ' . $returnCode . '). Output: ' . $outputStr;
            \Log::error($errorMsg);
            throw new \RuntimeException($errorMsg);
        }

        return $pdfPath;
    }

    /**
     * DOCX ichidagi EMF rasmlarni PNG ga aylantirish
     * PhpWord/DomPDF EMF formatini qo'llab-quvvatlamaydi
     */
    private function convertEmfImagesToPng(string $docxPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $emfFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/^word\/media\/.*\.emf$/i', $name)) {
                $emfFiles[] = $name;
            }
        }

        if (empty($emfFiles)) {
            $zip->close();
            return;
        }

        $tempDir = storage_path('app/temp');

        foreach ($emfFiles as $emfFile) {
            $emfData = $zip->getFromName($emfFile);
            if (!$emfData) {
                continue;
            }

            $pngData = null;

            // 1-usul: Imagick orqali EMF → PNG
            if (extension_loaded('imagick')) {
                try {
                    $tempEmf = $tempDir . '/' . uniqid('emf_') . '.emf';
                    file_put_contents($tempEmf, $emfData);

                    $imagick = new \Imagick();
                    $imagick->setResolution(150, 150);
                    $imagick->readImage($tempEmf);
                    $imagick->setImageFormat('png');
                    $pngData = $imagick->getImageBlob();
                    $imagick->clear();
                    @unlink($tempEmf);
                } catch (\Throwable $e) {
                    $pngData = null;
                }
            }

            // 2-usul: GD orqali 1x1 shaffof PNG placeholder
            if (!$pngData && extension_loaded('gd')) {
                $img = imagecreatetruecolor(200, 200);
                imagesavealpha($img, true);
                $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
                imagefill($img, 0, 0, $transparent);
                ob_start();
                imagepng($img);
                $pngData = ob_get_clean();
                imagedestroy($img);
            }

            if (!$pngData) {
                continue;
            }

            // EMF ni PNG bilan almashtirish
            $pngFile = preg_replace('/\.emf$/i', '.png', $emfFile);
            $zip->addFromString($pngFile, $pngData);
            $zip->deleteName($emfFile);

            // [Content_Types].xml da EMF → PNG ga yangilash
            $contentTypes = $zip->getFromName('[Content_Types].xml');
            if ($contentTypes) {
                // EMF extension uchun Override/Default mavjud bo'lsa PNG ga o'zgartirish
                if (strpos($contentTypes, 'Extension="emf"') !== false) {
                    $contentTypes = str_replace(
                        'Extension="emf" ContentType="image/x-emf"',
                        'Extension="png" ContentType="image/png"',
                        $contentTypes
                    );
                } elseif (strpos($contentTypes, 'Extension="png"') === false) {
                    // PNG extension hali yo'q bo'lsa qo'shish
                    $contentTypes = str_replace(
                        '</Types>',
                        '<Default Extension="png" ContentType="image/png"/></Types>',
                        $contentTypes
                    );
                }
                $zip->addFromString('[Content_Types].xml', $contentTypes);
            }

            // word/_rels/*.xml.rels da fayl nomini yangilash
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('/^word\/_rels\/.*\.rels$/', $name)) {
                    $relsContent = $zip->getFromName($name);
                    if ($relsContent && strpos($relsContent, basename($emfFile)) !== false) {
                        $relsContent = str_replace(basename($emfFile), basename($pngFile), $relsContent);
                        $zip->addFromString($name, $relsContent);
                    }
                }
            }
        }

        $zip->close();
    }

    /**
     * Word shablon ichidagi buzilgan ${...} makrolarni tuzatish
     *
     * Word ko'pincha ${m_num} ni XML run'lar orasida bo'ladi:
     *   <w:t>${m_</w:t></w:r><w:r><w:rPr>...</w:rPr><w:t>num}</w:t>
     * PhpWord fixBrokenMacros() buni har doim ham tuzatmaydi.
     */
    private function fixBrokenTemplateMacros(TemplateProcessor $processor): void
    {
        $reflection = new \ReflectionClass($processor);
        $property = $reflection->getProperty('tempDocumentMainPart');
        $property->setAccessible(true);

        $xml = $property->getValue($processor);
        $xml = $this->mergeBrokenMacros($xml);
        $property->setValue($processor, $xml);

        // Header/footer'larni ham tuzatish
        try {
            $headersProp = $reflection->getProperty('tempDocumentHeaders');
            $headersProp->setAccessible(true);
            $headers = $headersProp->getValue($processor);
            foreach ($headers as $key => $header) {
                $headers[$key] = $this->mergeBrokenMacros($header);
            }
            $headersProp->setValue($processor, $headers);
        } catch (\Throwable $e) {
            // Header yo'q bo'lsa e'tibor bermaslik
        }
    }

    /**
     * XML ichidagi bo'lingan ${...} makrolarni birlashtirish
     *
     * PCRE2 da \{ unclosed brace xatosi berishi mumkin,
     * shuning uchun \x7B (={) va \x7D (=}) hex kodlardan foydalanamiz.
     */
    private function mergeBrokenMacros(string $xml): string
    {
        // XML run break pattern: </w:t> ... <w:t>
        // # delimiter ishlatamiz chunki / XML ichida bor
        $runBreak = '<\\/w:t>.*?<w:t[^>]*>';

        // 1-qadam: $ va { orasidagi run break'ni olib tashlash
        // $</w:t>...<w:t>{ => ${
        $xml = preg_replace(
            '#\x24(' . $runBreak . ')\x7B#sU',
            "\x24\x7B",
            $xml
        ) ?? $xml;

        // 2-qadam: ${ va } orasidagi run break'larni takroriy olib tashlash
        for ($i = 0; $i < 15; $i++) {
            $before = $xml;
            $xml = preg_replace(
                '#(\x24\x7B[^\x7D<]*)' . $runBreak . '([^\x7D<]*\x7D)#sU',
                '$1$2',
                $xml
            ) ?? $before;

            if ($xml === $before) {
                break;
            }
        }

        // 3-qadam: ${m_num } kabi ichidagi bo'sh joylarni tozalash → ${m_num}
        $xml = preg_replace_callback(
            '#\x24\x7B([^<\x7D]+)\x7D#u',
            function ($m) {
                return '${' . trim($m[1]) . '}';
            },
            $xml
        ) ?? $xml;

        return $xml;
    }

    /**
     * cloneRow ishlamaganda qo'lda XML ichida jadval qatorlarini klonlash
     */
    private function manualCloneRow(TemplateProcessor $processor, $makeups, array $typeLabels): void
    {
        $reflection = new \ReflectionClass($processor);
        $property = $reflection->getProperty('tempDocumentMainPart');
        $property->setAccessible(true);

        $xml = $property->getValue($processor);

        // m_num, m_subject, m_type, m_date bo'lgan <w:tr> qatorni topish
        // Har qanday shaklda: ${m_num} yoki bo'lingan holda
        $rowPattern = '#<w:tr\b[^>]*>(?:(?!<w:tr\b).)*?m_num(?:(?!<w:tr\b).)*?</w:tr>#su';

        if (!preg_match($rowPattern, $xml, $match)) {
            // Qatorni topa olmadik — placeholder'larni tozalash
            \Log::warning('manualCloneRow: template row not found, clearing placeholders');
            try {
                $processor->setValue('m_num', '');
                $processor->setValue('m_subject', '');
                $processor->setValue('m_type', '');
                $processor->setValue('m_date', '');
            } catch (\Throwable $e) {
                // Ignore
            }
            return;
        }

        $templateRow = $match[0];
        $newRows = '';

        foreach ($makeups->values() as $i => $makeup) {
            $idx = $i + 1;
            $row = $templateRow;

            // Placeholder'larni almashtirish — str_replace ishlatamiz (regex emas)
            $dateStr = $this->formatMakeupDateRange($makeup);

            $row = str_replace(
                ['${m_num}', '${m_subject}', '${m_type}', '${m_date}'],
                [
                    (string) $idx,
                    htmlspecialchars($makeup->subject_name ?? '', ENT_XML1),
                    htmlspecialchars($typeLabels[$makeup->assessment_type] ?? $makeup->assessment_type, ENT_XML1),
                    htmlspecialchars($dateStr, ENT_XML1),
                ],
                $row
            );

            $newRows .= $row;
        }

        // Eski shablon qatorni yangi qatorlar bilan almashtirish
        $xml = str_replace($templateRow, $newRows, $xml);
        $property->setValue($processor, $xml);

        \Log::info('manualCloneRow: successfully cloned ' . $makeups->count() . ' rows');
    }

    /**
     * Makeup sana yoki range ni formatlash: "26.02.2026" yoki "26.02.2026 — 02.03.2026"
     */
    private function formatMakeupDateRange($makeup): string
    {
        if (!$makeup->makeup_date) {
            return 'Belgilanmagan';
        }

        $start = $makeup->makeup_date->format('d.m.Y');

        if ($makeup->makeup_end_date) {
            return $start . ' — ' . $makeup->makeup_end_date->format('d.m.Y');
        }

        return $start;
    }

    /**
     * LibreOffice (soffice) topish
     */
    private function findSoffice(): ?string
    {
        // To'liq yo'llarni bevosita tekshirish (which PHP muhitida ishlamasligi mumkin)
        $absolutePaths = ['/usr/bin/soffice', '/usr/local/bin/soffice', '/usr/bin/libreoffice', '/usr/lib/libreoffice/program/soffice'];

        foreach ($absolutePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // which orqali qidirish (fallback)
        exec('which soffice 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }

        return null;
    }

    /**
     * QR kod rasmini generatsiya qilish va vaqtinchalik faylga saqlash
     */
    public function generateQrImage(string $data): ?string
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $qrPath = $tempDir . '/' . uniqid('qr_') . '.png';

        // 1-usul: BaconQrCode + Imagick (faqat Imagick mavjud bo'lsa — haqiqiy PNG yaratadi)
        $darkRed = new \BaconQrCode\Renderer\Color\Rgb(220, 38, 38);
        $white = new \BaconQrCode\Renderer\Color\Rgb(255, 255, 255);
        $fill = \BaconQrCode\Renderer\RendererStyle\Fill::uniformColor($white, $darkRed);

        if (class_exists(\BaconQrCode\Writer::class) && extension_loaded('imagick')) {
            try {
                $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300, 1, null, null, $fill),
                    new \BaconQrCode\Renderer\Image\ImagickImageBackEnd()
                );
                $pngData = (new \BaconQrCode\Writer($renderer))->writeString($data);
                file_put_contents($qrPath, $pngData);
                return $qrPath;
            } catch (\Throwable $e) {
                // Imagick bilan xatolik — keyingi usulga o'tish
            }
        }

        // 2-usul: BaconQrCode SVG → GD orqali PNG ga aylantirish
        if (class_exists(\BaconQrCode\Writer::class) && extension_loaded('gd')) {
            try {
                $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300, 1, null, null, $fill),
                    new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
                );
                $svgData = (new \BaconQrCode\Writer($renderer))->writeString($data);

                // SVG ni vaqtinchalik faylga yozib, Imagick yoki GD orqali PNG ga aylantirish
                $tempSvg = $tempDir . '/' . uniqid('qr_svg_') . '.svg';
                file_put_contents($tempSvg, $svgData);

                // Imagick orqali SVG → PNG
                if (extension_loaded('imagick')) {
                    $imagick = new \Imagick();
                    $imagick->readImage($tempSvg);
                    $imagick->setImageFormat('png');
                    $imagick->writeImage($qrPath);
                    $imagick->clear();
                    @unlink($tempSvg);
                    return $qrPath;
                }

                @unlink($tempSvg);
            } catch (\Throwable $e) {
                // Keyingi usulga o'tish
            }
        }

        // 3-usul: Online API (eng ishonchli fallback)
        $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&color=DC2626&data=' . urlencode($data);
        $pngData = @file_get_contents($apiUrl);

        if ($pngData) {
            file_put_contents($qrPath, $pngData);
            return $qrPath;
        }

        // QR kod yaratib bo'lmadi — null qaytarish (shablon QR kodsiz davom etadi)
        return null;
    }
}
