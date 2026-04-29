<?php

namespace App\Services\Retake;

use App\Models\RetakeApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Qayta o'qish ariza tasdiqnoma PDF generatsiyasi (QR kod bilan).
 *
 * Triggered: ariza yakuniy approved bo'lganda (academic_dept_status='approved').
 * Output: storage/app/private/tasdiqnomalar/{application_id}.pdf
 *
 * QR kod URL:  {APP_URL}/verify/{verification_code}
 * QR ichida faqat URL — sezgir ma'lumot embedded emas (xavfsizlik).
 */
class RetakeTasdiqnomaService
{
    public const STORAGE_DIR = 'private/tasdiqnomalar';
    public const QR_SIZE = 180;

    /**
     * Tasdiqnoma PDF generatsiya qilish va yo'lini ariza yozuviga saqlash.
     *
     * @return string  storage path (storage/app/{path})
     */
    public function generate(RetakeApplication $application): string
    {
        if ($application->verification_code === null) {
            throw new \RuntimeException('Verification code yo\'q — ariza approved emas.');
        }

        // Aloqalar yuklangan bo'lishini ta'minlash
        $application->loadMissing(['student', 'retakeGroup.teacher']);

        $verifyUrl = $this->buildVerifyUrl($application->verification_code);
        $qrSvg = $this->generateQrSvg($verifyUrl);

        $pdf = Pdf::loadView('admin.retake.pdf.tasdiqnoma', [
            'application' => $application,
            'student' => $application->student,
            'group' => $application->retakeGroup,
            'teacher' => $application->retakeGroup?->teacher,
            'verifyUrl' => $verifyUrl,
            'qrSvg' => $qrSvg,
            'verificationCode' => $application->verification_code,
        ])->setPaper('a4', 'portrait');

        $relativePath = self::STORAGE_DIR . '/' . $application->id . '.pdf';
        $absolutePath = storage_path('app/' . $relativePath);

        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdf->save($absolutePath);

        $application->update(['tasdiqnoma_pdf_path' => $relativePath]);

        return $relativePath;
    }

    public function buildVerifyUrl(string $verificationCode): string
    {
        return rtrim(config('app.url', 'https://mark.tashmedunitf.uz'), '/') . '/verify/' . $verificationCode;
    }

    private function generateQrSvg(string $url): string
    {
        return QrCode::format('svg')
            ->size(self::QR_SIZE)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($url);
    }
}
