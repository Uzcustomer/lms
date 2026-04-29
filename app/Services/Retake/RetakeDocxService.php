<?php

namespace App\Services\Retake;

use App\Models\RetakeApplication;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Ariza DOCX hujjatini avto-generatsiya qiladi (Ariza-shablon.docx asosida).
 *
 * Ko'p fanli ariza uchun bitta umumiy DOCX yaratiladi:
 *   - Bir application_group_id bilan bog'langan barcha arizalar
 *   - {{subjects_list}} ichida fanlar vergul bilan
 *
 * Output: storage/app/private/retake-docs/{group_id}.docx
 *
 * Shablon: resources/templates/Retake/Ariza-shablon.docx
 * (PHPWord {{}} sintaksisi bilan, GenerateRetakeApplicationTemplate command'i orqali yaratiladi)
 */
class RetakeDocxService
{
    public const TEMPLATE_PATH = 'templates/Retake/Ariza-shablon.docx';
    public const STORAGE_DIR = 'private/retake-docs';

    /**
     * application_group_id bo'yicha bitta umumiy DOCX yaratish.
     * Group ichidagi har ariza yozuviga generated_doc_path o'rnatadi.
     *
     * @return string yaratilgan fayl yo'li (storage/app/{path})
     */
    public function generateForGroup(string $applicationGroupId): string
    {
        $applications = RetakeApplication::query()
            ->where('application_group_id', $applicationGroupId)
            ->with('student')
            ->orderBy('semester_id')
            ->orderBy('subject_name')
            ->get();

        if ($applications->isEmpty()) {
            throw new \RuntimeException("Application group not found: {$applicationGroupId}");
        }

        $firstApp = $applications->first();
        $student = $firstApp->student;
        if ($student === null) {
            throw new \RuntimeException('Student not loaded for application group.');
        }

        $templatePath = resource_path(self::TEMPLATE_PATH);
        if (! file_exists($templatePath)) {
            throw new \RuntimeException(
                "Ariza shabloni topilmadi: {$templatePath}. " .
                "php artisan retake:generate-template buyrug'i orqali yaratish kerak."
            );
        }

        $processor = new TemplateProcessor($templatePath);
        $processor->setMacroOpeningChars('{{');
        $processor->setMacroClosingChars('}}');

        $values = $this->buildPlaceholderValues($student, $applications);
        $processor->setValues($values);

        $relativePath = self::STORAGE_DIR . '/' . $applicationGroupId . '.docx';
        $absolutePath = storage_path('app/' . $relativePath);

        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $processor->saveAs($absolutePath);

        // Har ariza yozuviga umumiy DOCX yo'lini bog'lash
        $applications->each(function (RetakeApplication $app) use ($relativePath) {
            $app->update(['generated_doc_path' => $relativePath]);
        });

        return $relativePath;
    }

    /**
     * Bitta ariza uchun (legacy fallback — odatda generateForGroup ishlatiladi).
     */
    public function generateForApplication(RetakeApplication $application): string
    {
        return $this->generateForGroup($application->application_group_id);
    }

    /**
     * Placeholder qiymatlarini hisoblash:
     *   - faculty_name: talabaning fakulteti (department_name)
     *   - dean_name: dean_faculties pivot orqali fakultet dekanini topish
     *     (bir nechta dekan bo'lsa — birinchi)
     *   - group_name, student_full_name: talabadan
     *   - semester_number: birinchi arizadan (yoki turli xil bo'lsa — birlashtirgan)
     *   - subjects_list: vergul bilan ajratilgan
     *   - submission_date: birinchi arizaning submitted_at sanasi
     *
     * @param  Collection<int, RetakeApplication>  $applications
     * @return array<string, string>
     */
    private function buildPlaceholderValues(Student $student, Collection $applications): array
    {
        $first = $applications->first();
        $deanName = $this->resolveDeanName((int) ($student->department_id ?? 0));

        return [
            'faculty_name' => $student->department_name ?? '',
            'dean_name' => $deanName,
            'group_name' => $student->group_name ?? '',
            'student_full_name' => $student->full_name ?? '',
            'semester_number' => $this->resolveSemesterNumber($applications),
            'subjects_list' => $applications->pluck('subject_name')->filter()->implode(', '),
            'submission_date' => $first->submitted_at?->format('d.m.Y') ?? now()->format('d.m.Y'),
        ];
    }

    /**
     * Talaba fakultetiga biriktirilgan dekanlardan birinchisini topish.
     * dean_faculties pivot orqali (Teacher modeli).
     */
    private function resolveDeanName(int $departmentHemisId): string
    {
        if ($departmentHemisId === 0) {
            return '';
        }

        $teacher = Teacher::query()
            ->whereHas('deanFaculties', fn ($q) => $q->where('dean_faculties.department_hemis_id', $departmentHemisId))
            ->where('is_active', true)
            ->orderBy('id')
            ->first(['id', 'full_name']);

        return $teacher?->full_name ?? '';
    }

    /**
     * Bir nechta arizalar bir xil semestrdami yoki turlimi.
     * Spec: bitta {{semester_number}} placeholder — biz birinchi semestrning
     * raqamini olib qaytaramiz (talabada odatda barcha qarzdor fanlar bir
     * yo'nalishda bo'ladi).
     *
     * @param  Collection<int, RetakeApplication>  $applications
     */
    private function resolveSemesterNumber(Collection $applications): string
    {
        $semesters = $applications->pluck('semester_name')->filter()->unique();
        if ($semesters->count() === 1) {
            // "1-semestr" dan raqamni ajratib olamiz
            $name = $semesters->first();
            if (preg_match('/(\d+)/', $name, $m)) {
                return $m[1];
            }
            return $name;
        }
        // Bir nechta semestr — vergul bilan
        return $semesters->map(function (string $name) {
            return preg_match('/(\d+)/', $name, $m) ? $m[1] : $name;
        })->implode(', ');
    }
}
