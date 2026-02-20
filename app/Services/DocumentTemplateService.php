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
        $processor->setValue('order_number', '08-' . str_pad($excuse->id, 5, '0', STR_PAD_LEFT));
        $processor->setValue('academic_year', $academicYear);
        $processor->setValue('verification_url', route('absence-excuse.verify', $excuse->verification_token));

        // QR kod rasm sifatida
        if ($qrImagePath && file_exists($qrImagePath)) {
            $processor->setImageValue('qr_code', [
                'path' => $qrImagePath,
                'width' => 100,
                'height' => 100,
                'ratio' => true,
            ]);
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
        $tempPdf = $tempDir . '/' . uniqid('output_') . '.pdf';

        $processor->saveAs($tempDocx);

        // LibreOffice orqali PDF ga aylantirish
        $command = sprintf(
            'soffice --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($tempDocx)
        );

        exec($command, $output, $returnCode);

        // LibreOffice PDF faylni .docx nomi bilan yaratadi
        $generatedPdf = preg_replace('/\.docx$/', '.pdf', $tempDocx);

        if ($returnCode !== 0 || !file_exists($generatedPdf)) {
            // Tozalash
            @unlink($tempDocx);
            throw new \RuntimeException('PDF generatsiyada xatolik (LibreOffice). Return code: ' . $returnCode . '. Output: ' . implode("\n", $output));
        }

        // PDF ni kerakli joyga ko'chirish
        $pdfPath = 'absence-excuses/approved/' . $excuse->verification_token . '.pdf';
        $pdfContent = file_get_contents($generatedPdf);
        Storage::disk('public')->put($pdfPath, $pdfContent);

        // Vaqtinchalik fayllarni tozalash
        @unlink($tempDocx);
        @unlink($generatedPdf);

        return $pdfPath;
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

        // 1-usul: BaconQrCode
        if (class_exists(\BaconQrCode\Writer::class)) {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300, 1),
                new \BaconQrCode\Renderer\Image\EpsImageBackEnd()
            );

            // PNG uchun GD yoki Imagick kerak, EPS fallback
            if (class_exists(\BaconQrCode\Renderer\Image\ImagickImageBackEnd::class) && extension_loaded('imagick')) {
                $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300, 1),
                    new \BaconQrCode\Renderer\Image\ImagickImageBackEnd()
                );
            }

            try {
                $pngData = (new \BaconQrCode\Writer($renderer))->writeString($data);
                file_put_contents($qrPath, $pngData);
                return $qrPath;
            } catch (\Throwable $e) {
                // Fallback to online API
            }
        }

        // 2-usul: Online API
        $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=' . urlencode($data);
        $pngData = @file_get_contents($apiUrl);

        if ($pngData) {
            file_put_contents($qrPath, $pngData);
            return $qrPath;
        }

        return null;
    }
}
