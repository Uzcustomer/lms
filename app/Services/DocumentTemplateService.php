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

        $daysCount = $excuse->start_date->diffInDays($excuse->end_date) + 1;

        // PhpWord TemplateProcessor
        $processor = new TemplateProcessor($templatePath);

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
        $processor->setValue('academic_year', $academicYear);
        $processor->setValue('verification_url', route('absence-excuse.verify', $excuse->verification_token));

        // Buyruq raqami — jadvaldan tashqarida (AVVAL o'rnatiladi, cloneRow dan oldin)
        $processor->setValue('order_number', '08-' . str_pad($excuse->id, 5, '0', STR_PAD_LEFT));

        // Nazoratlar jadvali
        $makeups = $excuse->makeups()->orderBy('subject_name')->get();

        $typeLabels = [
            'jn' => 'JN',
            'mt' => 'MT',
            'oski' => 'YN(OSKE)',
            'test' => 'YN(Test)',
        ];

        // Tartiblash: Test/OSKI/MT bo'lgan fanlar avval, faqat JN bo'lgan fanlar keyin
        // Bir fan ichida JN birinchi, keyin boshqalar
        $subjectTypes = [];
        foreach ($makeups as $m) {
            if (!isset($subjectTypes[$m->subject_name])) {
                $subjectTypes[$m->subject_name] = false;
            }
            if ($m->assessment_type !== 'jn') {
                $subjectTypes[$m->subject_name] = true; // has non-JN
            }
        }

        $sortedMakeups = $makeups->sort(function ($a, $b) use ($subjectTypes) {
            // 1. Fan bilan non-JN borlar avval
            $aHasNonJn = $subjectTypes[$a->subject_name] ?? false;
            $bHasNonJn = $subjectTypes[$b->subject_name] ?? false;
            if ($aHasNonJn !== $bHasNonJn) {
                return $bHasNonJn <=> $aHasNonJn; // true (non-JN bor) avval
            }

            // 2. Fan nomi bo'yicha
            $nameCompare = strcmp($a->subject_name, $b->subject_name);
            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            // 3. Bir fan ichida: JN avval
            $aIsJn = $a->assessment_type === 'jn' ? 0 : 1;
            $bIsJn = $b->assessment_type === 'jn' ? 0 : 1;
            return $aIsJn <=> $bIsJn;
        })->values();

        $makeupCount = $sortedMakeups->count();

        if ($makeupCount > 0) {
            $processor->cloneRow('m_num', $makeupCount);

            foreach ($sortedMakeups as $i => $makeup) {
                $idx = $i + 1;
                $processor->setValue("m_num#{$idx}", (string) $idx);
                $processor->setValue("m_subject#{$idx}", $makeup->subject_name ?? '');
                $processor->setValue("m_type#{$idx}", $typeLabels[$makeup->assessment_type] ?? $makeup->assessment_type);

                if ($makeup->assessment_type === 'jn') {
                    $dateStr = $excuse->start_date->format('d.m.Y') . ' - ' . $excuse->end_date->format('d.m.Y');
                } else {
                    $dateStr = $makeup->makeup_date
                        ? $makeup->makeup_date->format('d.m.Y')
                        : 'Belgilanmagan';
                }
                $processor->setValue("m_date#{$idx}", $dateStr);
            }
        } else {
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
        if (class_exists(\BaconQrCode\Writer::class) && extension_loaded('imagick')) {
            try {
                $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300, 1),
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
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300, 1),
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
        $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=' . urlencode($data);
        $pngData = @file_get_contents($apiUrl);

        if ($pngData) {
            file_put_contents($qrPath, $pngData);
            return $qrPath;
        }

        // QR kod yaratib bo'lmadi — null qaytarish (shablon QR kodsiz davom etadi)
        return null;
    }
}
