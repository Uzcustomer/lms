<?php

namespace App\Services\Retake;

use App\Models\DocumentVerification;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\Student;
use App\Models\Teacher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Tasdiqnoma (PDF + QR) va ariza (DOCX) generatsiyasi.
 *
 * Hujjatlar uchchalasi (dekan + registrator + o'quv bo'limi) tasdiqlagandan
 * keyin generatsiya qilinadi. Bitta application_group uchun bittadan
 * (har talaba o'z arizasiga bitta DOCX va bitta PDF tasdiqnoma oladi).
 */
class RetakeDocumentService
{
    public const STORAGE_DISK = 'public';
    public const DOCX_DIR = 'retake/docx';
    public const PDF_DIR = 'retake/certificates';
    public const QR_DIR = 'retake/qr';

    /**
     * Guruh uchun hujjatlarni generatsiya qilish.
     * Faqat barcha arizalar yakuniy holatda (pending emas) bo'lganda chaqiriladi.
     * Tasdiqnoma faqat bitta yoki ko'p APPROVED ariza bo'lsa yaratiladi.
     */
    public function generateForGroup(RetakeApplicationGroup $group, ?Teacher $generator = null): RetakeApplicationGroup
    {
        $group->load(['student', 'applications.retakeGroup.teacher']);

        if (!$group->student) {
            return $group;
        }

        // 1. DOCX (ariza shabloni) — qarorlarga bog'liq emas, har doim mavjud bo'lsin
        if (!$group->docx_path) {
            $group->docx_path = $this->generateDocx($group);
        }

        // 2. PDF tasdiqnoma — kamida bitta tasdiqlangan ariza bo'lsa generatsiya
        //    qilinadi. Har safar qayta generatsiya qilinadi, chunki har bir yangi
        //    tasdiqlangan fan PDF tarkibiga qo'shilishi kerak (signature kesh
        //    ishlatishni biz hozir yodda saqlamaymiz — PDF yaratish arzon).
        $approved = $group->applications->where('final_status', 'approved');
        if ($approved->isNotEmpty()) {
            $verification = null;
            if (!$group->verification_token) {
                $verification = $this->createVerification($group, $approved, $generator);
                $group->verification_token = $verification->token;
            } else {
                $verification = DocumentVerification::where('token', $group->verification_token)->first();
            }

            $group->pdf_certificate_path = $this->generatePdfCertificate($group, $approved);

            if ($verification) {
                $verification->update(['document_path' => $group->pdf_certificate_path]);
            }
        }

        $group->save();

        return $group;
    }

    /**
     * DOCX (ariza) generatsiyasi — foydalanuvchi bergan shablon asosida
     * dasturiy ravishda yaratiladi (template fayl kerak emas).
     */
    public function generateDocx(RetakeApplicationGroup $group): string
    {
        $student = $group->student;
        $applications = $group->applications->sortBy('semester_id')->values();

        // Dekan F.I.Sh. ni topish: avval ariza ustidan (allaqachon qaror qilingan
        // bo'lsa), aks holda — talaba fakultetiga biriktirilgan dekan teacher.
        $deanName = $applications
            ->pluck('dean_user_name')
            ->filter()
            ->first();
        if (!$deanName) {
            $deanName = $this->lookupFacultyDeanName($student) ?? '';
        }

        $facultyBase = $this->stripFacultetiSuffix($student->department_name ?? '');
        $groupName = $student->group_name ?? '';
        $studentFullName = $student->full_name ?? '';
        $submissionDate = $group->created_at->format('Y-m-d');

        // "1-semestr Anatomiya (6.0 kr), 2-semestr Patanatomiya (5.0 kr)"
        $subjectsList = $applications
            ->map(fn (RetakeApplication $a) => sprintf(
                '%s %s (%.1f kr)',
                $a->semester_name,
                $a->subject_name,
                (float) $a->credit
            ))
            ->implode(', ');

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop' => 1134,    // ~2 sm
            'marginBottom' => 1134,
            'marginLeft' => 1700,   // ~3 sm
            'marginRight' => 1134,  // ~2 sm
        ]);

        // ─── Yuqori o'ng — manzil bloki (jadval ichida toza ko'rinadi) ───
        // Ikki ustunli: chap ustun bo'sh, o'ng ustun manzilni saqlaydi.
        // Bu yondashuv right-aligned lentaning notekis ko'rinishini bartaraf etadi.
        $phpWord->addTableStyle('arizaAddressTable', [
            'borderSize' => 0,
            'cellMargin' => 0,
            'alignment' => Jc::END,
        ]);

        $addressTable = $section->addTable('arizaAddressTable');
        $addressTable->addRow();
        $addressTable->addCell(3500); // chap ustun, bo'sh
        $rightCell = $addressTable->addCell(5800);

        $addrTextStyle = ['spaceAfter' => 60, 'lineHeight' => 1.2];
        $addrParaStyle = ['alignment' => Jc::CENTER, 'spaceAfter' => 60, 'lineHeight' => 1.2];

        $rightCell->addText('Toshkent davlat tibbiyot universiteti', null, $addrParaStyle);
        $rightCell->addText('Termiz filiali', null, $addrParaStyle);
        $rightCell->addText("{$facultyBase} fakulteti dekani", null, $addrParaStyle);

        // Dekan F.I.Sh. + "ga" — bitta qatorda, butunlay qalin
        $deanLine = $rightCell->addTextRun($addrParaStyle);
        $deanLine->addText(
            ($deanName !== '' ? $deanName : '_______________________') . 'ga',
            ['bold' => true]
        );

        $rightCell->addTextBreak(1);

        $rightCell->addText("{$facultyBase} fakulteti", null, $addrParaStyle);
        $rightCell->addText("{$groupName}-guruh talabasi", null, $addrParaStyle);

        // Talaba F.I.Sh. + "dan" — qalin
        $stuLine = $rightCell->addTextRun($addrParaStyle);
        $stuLine->addText($studentFullName . 'dan', ['bold' => true]);

        $section->addTextBreak(2);

        // ─── ARIZA sarlavhasi ───
        $section->addText('ARIZA', ['bold' => true, 'size' => 16], [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 240,
        ]);

        // ─── Asosiy matn ───
        $bodyStyle = [
            'alignment' => Jc::BOTH,
            'indentation' => ['firstLine' => 720],
            'lineHeight' => 1.5,
            'spaceAfter' => 120,
        ];

        $section->addText(
            "Men, {$facultyBase} fakulteti {$groupName}-guruh talabasi {$studentFullName}, "
            . "ushbu ariza orqali shuni ma'lum qilamanki, akademik qarzdorligim mavjud bo'lgan "
            . "{$subjectsList} fan(lar)ini o'z hisobimdan qayta o'qish uchun ruxsat berishingizni so'rayman.",
            null,
            $bodyStyle
        );

        $section->addText(
            "Mazkur qayta o'qish uchun to'lov xabarnomasi ilova qilinadi.",
            null,
            $bodyStyle
        );

        $section->addTextBreak(3);

        // ─── Imzo bloki: jadval — talaba (chap) | sana (o'ng) ───
        $phpWord->addTableStyle('arizaSignTable', [
            'borderSize' => 0,
            'cellMargin' => 0,
        ]);
        $signTable = $section->addTable('arizaSignTable');
        $signTable->addRow();

        $signLeft = $signTable->addCell(5500);
        $signLeft->addText(
            'Talaba:  ' . $studentFullName,
            null,
            ['alignment' => Jc::START, 'spaceAfter' => 0]
        );

        $signRight = $signTable->addCell(3800);
        $signRight->addText(
            $submissionDate,
            null,
            ['alignment' => Jc::END, 'spaceAfter' => 0]
        );

        // Saqlash
        $fileName = sprintf('ariza_%d_%s.docx', $group->id, Str::slug($studentFullName ?: 'talaba'));
        $relPath = self::DOCX_DIR . '/' . $fileName;
        $absPath = Storage::disk(self::STORAGE_DISK)->path($relPath);

        $this->ensureDirectoryExists(dirname($absPath));

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($absPath);

        return $relPath;
    }

    /**
     * Talaba fakultetiga biriktirilgan dekanning F.I.Sh.ni topadi (dean_faculties).
     */
    private function lookupFacultyDeanName(?Student $student): ?string
    {
        if (!$student || !$student->department_id) {
            return null;
        }

        $dean = Teacher::query()
            ->where('status', true)
            ->whereHas('roles', fn ($q) => $q->where('name', \App\Enums\ProjectRole::DEAN->value))
            ->whereHas('deanFaculties', fn ($q) => $q->where('departments.department_hemis_id', $student->department_id))
            ->first();

        return $dean?->full_name;
    }

    /**
     * "Xalqaro talim fakulteti" → "Xalqaro talim". Boshqa joyda " fakulteti" qo'shilsa,
     * takrorlanmasligi uchun nomdan oxirgi " fakulteti" so'zi olib tashlanadi.
     */
    private function stripFacultetiSuffix(string $name): string
    {
        return trim(preg_replace('/\s*fakulteti\s*$/iu', '', $name));
    }

    /**
     * Tasdiqnoma PDF generatsiyasi (QR kod bilan, faqat tasdiqlangan fanlar).
     */
    public function generatePdfCertificate(RetakeApplicationGroup $group, $approvedApps, string $locale = 'uz'): string
    {
        $student = $group->student;
        $verifyUrl = route('document.verify', $group->verification_token);

        // QR kodni SVG fayl sifatida saqlab, dompdf'ga absolyut yo'l beramiz —
        // dompdf SVG'ni inline emas, <img src="abs/path"> orqali ishonchli ko'rsatadi.
        $qrSvg = QrCode::format('svg')
            ->size(220)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($verifyUrl);

        $qrFileName = sprintf('qr_%d.svg', $group->id);
        $qrRelPath = self::QR_DIR . '/' . $qrFileName;
        Storage::disk(self::STORAGE_DISK)->put($qrRelPath, $qrSvg);
        $qrAbsPath = Storage::disk(self::STORAGE_DISK)->path($qrRelPath);

        $signers = $this->collectSigners($approvedApps);
        $logoAbsPath = $this->resolveLogoPath();

        // Lokal'ni vaqtincha o'rnatamiz, render tugagach qaytaramiz.
        $previousLocale = app()->getLocale();
        app()->setLocale($locale);

        try {
            $pdf = Pdf::loadView('pdf.retake-certificate', [
                'group' => $group,
                'student' => $student,
                'approvedApps' => $approvedApps,
                'verifyUrl' => $verifyUrl,
                'qrAbsPath' => $qrAbsPath,
                'logoAbsPath' => $logoAbsPath,
                'signers' => $signers,
                'verificationToken' => $group->verification_token,
                'totalCredits' => $approvedApps->sum(fn ($a) => (float) $a->credit),
                'totalAmount' => (float) $group->receipt_amount,
                'locale' => $locale,
            ])->setPaper('A4');

            $suffix = $locale === 'uz' ? '' : '_' . $locale;
            $fileName = sprintf('ruxsatnoma_%d_%s%s.pdf', $group->id, Str::slug($student->full_name ?: 'talaba'), $suffix);
            $relPath = self::PDF_DIR . '/' . $fileName;
            $absPath = Storage::disk(self::STORAGE_DISK)->path($relPath);

            $this->ensureDirectoryExists(dirname($absPath));
            $pdf->save($absPath);

            return $relPath;
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    /**
     * Dekan / Registrator / O'quv bo'limi imzolari uchun F.I.Sh. va sanani yig'adi.
     */
    private function collectSigners($approvedApps): array
    {
        $first = $approvedApps->first();
        if (!$first) {
            return ['dean' => null, 'registrar' => null, 'academic' => null];
        }

        return [
            'dean' => [
                'name' => $first->dean_user_name,
                'date' => optional($first->dean_decision_at)->format('Y-m-d'),
            ],
            'registrar' => [
                'name' => $first->registrar_user_name,
                'date' => optional($first->registrar_decision_at)->format('Y-m-d'),
            ],
            'academic' => [
                'name' => $first->academic_dept_user_name,
                'date' => optional($first->academic_dept_decision_at)->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Universitet logotipini topish — public/logo.png yoki public/images/logo.png.
     */
    private function resolveLogoPath(): ?string
    {
        foreach (['logo.png', 'images/logo.png', 'logo.svg'] as $rel) {
            $abs = public_path($rel);
            if (is_file($abs)) {
                return $abs;
            }
        }
        return null;
    }

    /**
     * DocumentVerification yozuvini yaratish (mavjud public verify tizimini qayta ishlatish).
     */
    private function createVerification(RetakeApplicationGroup $group, $approvedApps, ?Teacher $generator): DocumentVerification
    {
        $student = $group->student;
        $subjectNames = $approvedApps->pluck('subject_name')->implode(', ');
        $semesterNames = $approvedApps->pluck('semester_name')->unique()->implode(', ');
        $groupNames = $approvedApps->pluck('retakeGroup.name')->filter()->unique()->implode(', ');

        return DocumentVerification::createForDocument([
            'document_type' => 'retake_certificate',
            'subject_name' => $subjectNames,
            'group_names' => $groupNames ?: null,
            'semester_name' => $semesterNames ?: null,
            'department_name' => $student->department_name ?? null,
            'generated_by' => $generator?->full_name ?? 'system',
            'meta' => [
                'application_group_id' => $group->id,
                'group_uuid' => $group->group_uuid,
                'student_hemis_id' => (int) $group->student_hemis_id,
                'student_full_name' => $student->full_name ?? null,
                'specialty_name' => $student->specialty_name ?? null,
                'level_name' => $student->level_name ?? $student->level_code ?? null,
                'student_group_name' => $student->group_name ?? null,
                'subjects' => $approvedApps->map(fn (RetakeApplication $a) => [
                    'subject_id' => $a->subject_id,
                    'subject_name' => $a->subject_name,
                    'semester_name' => $a->semester_name,
                    'credit' => (float) $a->credit,
                    'retake_group_id' => $a->retake_group_id,
                    'retake_group_name' => $a->retakeGroup?->name,
                    'teacher_name' => $a->retakeGroup?->teacher_name,
                    'start_date' => $a->retakeGroup?->start_date?->format('Y-m-d'),
                    'end_date' => $a->retakeGroup?->end_date?->format('Y-m-d'),
                ])->all(),
                'receipt_amount' => (float) $group->receipt_amount,
                'credit_price_at_time' => (float) $group->credit_price_at_time,
            ],
        ]);
    }

    private function ensureDirectoryExists(string $absDir): void
    {
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
    }
}
