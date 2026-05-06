<?php

namespace App\Services\Retake;

use App\Models\RetakeApplication;
use App\Models\RetakeGrade;
use App\Models\RetakeGroup;
use App\Models\RetakeMustaqilSubmission;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Qayta o'qish jurnali — kunlik baholar.
 *
 * Asosiy mantiq:
 *  - Guruh `start_date` dan `end_date` gacha har kun jurnalda alohida ustun
 *  - O'qituvchi guruh muddati ichida istalgan kunga baho qo'ya oladi (oxirgi
 *    kungacha, ya'ni guruh muddati ichida tahrirlash erkin)
 *  - Guruh tugagandan keyin (status=completed) yoki end_date'dan keyin
 *    baho qo'yib bo'lmaydi (faqat super-admin override)
 */
class RetakeJournalService
{
    /**
     * Guruh ichidagi kunlar (start_date dan end_date gacha).
     *
     * @return array<int, string>  Y-m-d formatdagi sanalar
     */
    public function lessonDates(RetakeGroup $group): array
    {
        if (!$group->start_date || !$group->end_date) {
            return [];
        }

        $dates = [];
        $cursor = Carbon::parse($group->start_date);
        $end = Carbon::parse($group->end_date);
        while ($cursor->lte($end)) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }
        return $dates;
    }

    /**
     * Guruhdagi tasdiqlangan arizalar (talaba ro'yxati).
     *
     * @return Collection<RetakeApplication>
     */
    public function applications(RetakeGroup $group): Collection
    {
        return RetakeApplication::query()
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->with(['group.student'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Guruh uchun barcha baholar — [application_id => [Y-m-d => RetakeGrade]] xarita.
     */
    public function gradesMap(RetakeGroup $group): array
    {
        $rows = RetakeGrade::query()
            ->where('retake_group_id', $group->id)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->lesson_date)->format('Y-m-d');
            $map[$row->application_id][$date] = $row;
        }
        return $map;
    }

    /**
     * Guruhga tegishli o'qituvchi shu actor ekanligini tekshirish.
     */
    public function isAssignedTeacher(RetakeGroup $group, Teacher $teacher): bool
    {
        return (int) $group->teacher_id === (int) $teacher->id;
    }

    /**
     * Guruh hozir tahrir qilinishi mumkinmi?
     * (start_date <= bugun <= end_date) yoki status `forming`/`scheduled`/`in_progress`,
     * va guruh `is_locked` emas.
     */
    public function isEditable(RetakeGroup $group): bool
    {
        if ($group->is_locked) {
            return false;
        }
        if (!$group->start_date || !$group->end_date) {
            return false;
        }
        $today = Carbon::today();
        if ($group->end_date->lt($today)) {
            return false;
        }
        return in_array($group->status, [
            RetakeGroup::STATUS_FORMING,
            RetakeGroup::STATUS_SCHEDULED,
            RetakeGroup::STATUS_IN_PROGRESS,
        ], true);
    }

    /**
     * Bitta katakka baho qo'yish/tahrirlash.
     */
    public function saveGrade(
        RetakeGroup $group,
        int $applicationId,
        string $lessonDate,
        ?float $grade,
        ?string $comment,
        Teacher $actor,
        bool $isAdmin = false,
    ): RetakeGrade {
        // Sana guruh muddati ichida ekanini tekshirish
        $valid = in_array($lessonDate, $this->lessonDates($group), true);
        if (!$valid) {
            throw ValidationException::withMessages([
                'lesson_date' => 'Sana guruh muddati ichida bo\'lishi kerak (' . $group->start_date->format('Y-m-d') . ' dan ' . $group->end_date->format('Y-m-d') . ' gacha)',
            ]);
        }

        // Tahrir mumkinmi?
        if (!$isAdmin && !$this->isEditable($group)) {
            throw ValidationException::withMessages([
                'group' => 'Bu guruh muddati tugagan, baho qo\'yib bo\'lmaydi',
            ]);
        }

        // Ariza guruhga tegishli ekanini tekshirish
        $app = RetakeApplication::where('id', $applicationId)
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->first();
        if (!$app) {
            throw ValidationException::withMessages([
                'application_id' => 'Ariza guruhga tegishli emas yoki tasdiqlanmagan',
            ]);
        }

        // Baho oralig'i 0..100
        if ($grade !== null && ($grade < 0 || $grade > 100)) {
            throw ValidationException::withMessages([
                'grade' => 'Baho 0 dan 100 gacha bo\'lishi kerak',
            ]);
        }

        return RetakeGrade::updateOrCreate(
            [
                'retake_group_id' => $group->id,
                'application_id' => $app->id,
                'lesson_date' => $lessonDate,
            ],
            [
                'student_hemis_id' => $app->student_hemis_id,
                'grade' => $grade,
                'comment' => $comment,
                'graded_by_user_id' => $actor->id,
                'graded_by_name' => $actor->full_name,
                'graded_at' => now(),
            ]
        );
    }

    /**
     * Mustaqil ta'lim — guruh uchun barcha submissions [application_id => row].
     */
    public function mustaqilMap(RetakeGroup $group): array
    {
        return RetakeMustaqilSubmission::query()
            ->where('retake_group_id', $group->id)
            ->get()
            ->keyBy('application_id')
            ->all();
    }

    /**
     * Talaba mustaqil ta'lim faylini yuklaydi.
     */
    public function submitMustaqil(
        RetakeGroup $group,
        Student $student,
        UploadedFile $file,
        ?string $comment = null,
    ): RetakeMustaqilSubmission {
        // Talaba shu guruhda?
        $app = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->first();
        if (!$app) {
            throw ValidationException::withMessages([
                'group' => 'Siz bu guruhga biriktirilmagansiz',
            ]);
        }

        // Guruh muddati tugagan bo'lsa yuklash mumkin emas
        if (!$this->isEditable($group)) {
            throw ValidationException::withMessages([
                'group' => 'Guruh muddati tugagan, fayl yuklash mumkin emas',
            ]);
        }

        // Fayl tekshirish
        $maxBytes = RetakeMustaqilSubmission::MAX_FILE_MB * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'Fayl hajmi ' . RetakeMustaqilSubmission::MAX_FILE_MB . ' MB dan oshmasligi kerak',
            ]);
        }
        $allowedExt = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowedExt, true)) {
            throw ValidationException::withMessages([
                'file' => 'Faqat PDF, DOC, DOCX, JPG, PNG, ZIP, RAR fayllar yuklanishi mumkin',
            ]);
        }

        // Eski faylni o'chirish (qayta yuklayotgan bo'lsa)
        $existing = RetakeMustaqilSubmission::where('retake_group_id', $group->id)
            ->where('application_id', $app->id)
            ->first();
        if ($existing && $existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $path = $file->store("retake/mustaqil/{$group->id}", 'public');

        return RetakeMustaqilSubmission::updateOrCreate(
            [
                'retake_group_id' => $group->id,
                'application_id' => $app->id,
            ],
            [
                'student_hemis_id' => $student->hemis_id,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'student_comment' => $comment,
                'submitted_at' => now(),
                // Qayta yuklaganda eski bahoni saqlab qoldirmaslik (qayta baholash uchun)
                'grade' => null,
                'teacher_comment' => null,
                'graded_by_user_id' => null,
                'graded_by_name' => null,
                'graded_at' => null,
            ]
        );
    }

    /**
     * Guruhni "lock" qilish — kunlik baholar va mustaqil ta'lim baholari
     * yakuniy hisoblanadi va keyin tahrirlash mumkin emas.
     * Har talaba uchun amaliyot o'rtachasini hisoblaymiz.
     */
    public function lockGroup(RetakeGroup $group, Teacher $actor): RetakeGroup
    {
        if ($group->is_locked) {
            throw ValidationException::withMessages([
                'group' => 'Guruh allaqachon yopilgan',
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($group, $actor) {
            // Har bir tasdiqlangan ariza uchun amaliyot o'rtachasini hisoblab,
            // mustaqil bahosini ham olib, dastlabki yakuniy bahoni qo'yamiz.
            // (OSKE/Test test markaziga qo'yilgach yakuniy yana yangilanadi.)
            $applications = $this->applications($group);
            $gradesMap = $this->gradesMap($group);
            $mustaqilMap = $this->mustaqilMap($group);

            foreach ($applications as $app) {
                $rowGrades = collect($gradesMap[$app->id] ?? [])
                    ->map(fn ($g) => $g->grade)
                    ->filter(fn ($v) => $v !== null);
                $amaliyotAvg = $rowGrades->isNotEmpty() ? round($rowGrades->avg(), 2) : null;

                $mustaqilGrade = ($mustaqilMap[$app->id] ?? null)?->grade;
                $mustaqilVal = $mustaqilGrade !== null ? (float) $mustaqilGrade : null;

                // Dastlabki yakuniy hisob (OSKE/Test'siz):
                //   Sinov fan → amaliyot avg + mustaqil avg / 2
                //   Boshqalari → amaliyot va mustaqil oraliq, OSKE/Test keyinroq qo'shiladi
                $components = collect([$amaliyotAvg, $mustaqilVal])->filter(fn ($v) => $v !== null);
                $preliminary = $components->isNotEmpty() ? round($components->avg(), 2) : null;

                $app->update([
                    'final_grade_value' => $preliminary,
                    'final_grade_set_at' => now(),
                ]);
            }

            $group->update([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by_user_id' => $actor->id,
                'locked_by_name' => $actor->full_name,
            ]);
        });

        return $group->refresh();
    }

    /**
     * Lock'ni bekor qilish (faqat super-admin).
     */
    public function unlockGroup(RetakeGroup $group): RetakeGroup
    {
        $group->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by_user_id' => null,
            'locked_by_name' => null,
        ]);
        return $group->refresh();
    }

    /**
     * Test markaziga vedomost yuborish (yopilgan guruhlar uchun).
     */
    public function sendToTestMarkazi(RetakeGroup $group, Teacher $actor): RetakeGroup
    {
        if (!$group->is_locked) {
            throw ValidationException::withMessages([
                'group' => 'Avval guruhni yopish (yakuniy yuborish) kerak',
            ]);
        }
        if ($group->sent_to_test_markazi_at) {
            throw ValidationException::withMessages([
                'group' => 'Vedomost allaqachon test markaziga yuborilgan',
            ]);
        }

        $group->update([
            'sent_to_test_markazi_at' => now(),
            'sent_to_test_markazi_by' => $actor->id,
        ]);

        return $group->refresh();
    }

    /**
     * Test markazi OSKE va TEST natijalarini yozadi.
     * Yakuniy bahoni qayta hisoblaymiz.
     */
    public function saveOskeTestScore(
        RetakeApplication $app,
        ?float $oskeScore,
        ?float $testScore,
        Teacher $actor,
    ): RetakeApplication {
        if ($oskeScore !== null && ($oskeScore < 0 || $oskeScore > 100)) {
            throw ValidationException::withMessages(['oske_score' => 'OSKE 0..100']);
        }
        if ($testScore !== null && ($testScore < 0 || $testScore > 100)) {
            throw ValidationException::withMessages(['test_score' => 'TEST 0..100']);
        }

        $app->update([
            'oske_score' => $oskeScore,
            'test_score' => $testScore,
        ]);

        // Yakuniy bahoni qayta hisoblaymiz
        $group = $app->retakeGroup;
        if ($group) {
            $rowGrades = RetakeGrade::query()
                ->where('retake_group_id', $group->id)
                ->where('application_id', $app->id)
                ->whereNotNull('grade')
                ->pluck('grade');
            $amaliyotAvg = $rowGrades->isNotEmpty() ? round($rowGrades->avg(), 2) : null;

            $mustaqil = \App\Models\RetakeMustaqilSubmission::query()
                ->where('retake_group_id', $group->id)
                ->where('application_id', $app->id)
                ->first();
            $mustaqilVal = $mustaqil?->grade !== null ? (float) $mustaqil->grade : null;

            $components = collect([$amaliyotAvg, $mustaqilVal, $oskeScore, $testScore])
                ->filter(fn ($v) => $v !== null);

            $final = $components->isNotEmpty() ? round($components->avg(), 2) : null;

            $app->update([
                'final_grade_value' => $final,
                'final_grade_set_at' => now(),
            ]);
        }

        return $app->refresh();
    }

    /**
     * O'qituvchi mustaqil ta'lim ishini baholaydi.
     */
    public function gradeMustaqil(
        RetakeGroup $group,
        int $applicationId,
        ?float $grade,
        ?string $comment,
        Teacher $actor,
        bool $isAdmin = false,
    ): RetakeMustaqilSubmission {
        if (!$isAdmin && !$this->isEditable($group)) {
            throw ValidationException::withMessages([
                'group' => 'Guruh muddati tugagan, baho qo\'yib bo\'lmaydi',
            ]);
        }

        if ($grade !== null && ($grade < 0 || $grade > 100)) {
            throw ValidationException::withMessages([
                'grade' => 'Baho 0 dan 100 gacha bo\'lishi kerak',
            ]);
        }

        $submission = RetakeMustaqilSubmission::query()
            ->where('retake_group_id', $group->id)
            ->where('application_id', $applicationId)
            ->first();

        if (!$submission || !$submission->file_path) {
            throw ValidationException::withMessages([
                'submission' => 'Talaba hali fayl yuklamagan',
            ]);
        }

        $submission->update([
            'grade' => $grade,
            'teacher_comment' => $comment,
            'graded_by_user_id' => $actor->id,
            'graded_by_name' => $actor->full_name,
            'graded_at' => now(),
        ]);

        return $submission->refresh();
    }

    /**
     * Yakuniy qaydnomani Excel formatida (mavjud yn_qaydnoma shabloni asosida) yaratadi.
     * Existing Admin\JournalController::exportYnQaydnoma logikasiga moslashtirilgan.
     *
     * @return array{path: string, filename: string, relPath: string}
     */
    /**
     * Standart (default) vaznlarni assessment_type bo'yicha qaytaradi.
     */
    public function defaultWeights(RetakeGroup $group): array
    {
        switch ($group->assessment_type) {
            case 'oske':
                return ['jn' => 30, 'mt' => 10, 'on' => 0, 'oski' => 60, 'test' => 0];
            case 'test':
                return ['jn' => 30, 'mt' => 10, 'on' => 0, 'oski' => 0,  'test' => 60];
            case 'oske_test':
                return ['jn' => 30, 'mt' => 10, 'on' => 0, 'oski' => 30, 'test' => 30];
            case 'sinov_fan':
            default:
                return ['jn' => 70, 'mt' => 30, 'on' => 0, 'oski' => 0,  'test' => 0];
        }
    }

    /**
     * @param  array{jn:int,mt:int,on:int,oski:int,test:int}|null  $weights
     */
    public function buildVedomostExcel(RetakeGroup $group, ?array $weights = null): array
    {
        $templatePath = public_path('templates/yn_qaydnoma (1).xlsx');
        if (!file_exists($templatePath)) {
            throw ValidationException::withMessages([
                'template' => 'Vedomost shabloni topilmadi: templates/yn_qaydnoma (1).xlsx',
            ]);
        }

        $applications = $this->applications($group);
        $gradesMap = $this->gradesMap($group);
        $mustaqilMap = $this->mustaqilMap($group);

        // Vaznlar
        $w = $weights ?? $this->defaultWeights($group);
        $weightJn   = (int) ($w['jn']   ?? 0);
        $weightMt   = (int) ($w['mt']   ?? 0);
        $weightOn   = (int) ($w['on']   ?? 0);
        $weightOski = (int) ($w['oski'] ?? 0);
        $weightTest = (int) ($w['test'] ?? 0);

        $abbreviateName = function ($fullName) {
            $parts = preg_split('/\s+/', trim((string) $fullName));
            if (count($parts) <= 1) return $fullName;
            $surname = $parts[0];
            $initials = '';
            for ($i = 1; $i < count($parts); $i++) {
                $word = $parts[$i];
                $up2 = mb_strtoupper(mb_substr($word, 0, 2));
                if ($up2 === 'SH' || $up2 === 'CH') {
                    $initials .= mb_substr($word, 0, 1) . mb_strtolower(mb_substr($word, 1, 1)) . '.';
                } else {
                    $initials .= mb_strtoupper(mb_substr($word, 0, 1)) . '.';
                }
            }
            return $surname . ' ' . $initials;
        };

        // Ko'pchilik faculty / specialty / kurs / semestr
        $studentInfo = $applications->map(fn ($a) => $a->group->student ?? null)->filter();
        $pickMostCommon = function (Collection $values, string $field): string {
            $names = $values->pluck($field)->filter()->countBy();
            return (string) ($names->sortDesc()->keys()->first() ?? '');
        };

        $facultyFullName = $pickMostCommon($studentInfo, 'department_name');
        $facultyName = preg_replace('/\s*fakulteti?\s*$/iu', '', $facultyFullName);
        $specialtyName = $pickMostCommon($studentInfo, 'specialty_name');
        $levelName = $pickMostCommon($studentInfo, 'level_name');
        $semesterName = $pickMostCommon($studentInfo, 'semester_name');
        $kurs = preg_match('/(\d+)/', $levelName, $m) ? $m[1] : '';
        $semesterInYear = preg_match('/(\d+)/', $semesterName, $m) ? (int) $m[1] : '';

        $teacherNameAbbr = $group->teacher_name ? $abbreviateName($group->teacher_name) : '';

        // Dekan: ko'pchilik fakultet hemis_id'dan dekanni qidiramiz
        $abbrFio = function (string $fullName): string {
            $parts = preg_split('/\s+/', trim($fullName));
            if (count($parts) < 2) return $fullName;
            $surname = mb_strtoupper(mb_substr($parts[0], 0, 1)) . mb_strtolower(mb_substr($parts[0], 1));
            $initials = '';
            for ($i = 1; $i < count($parts); $i++) {
                $initials .= mb_strtoupper(mb_substr($parts[$i], 0, 1)) . '.';
            }
            return $initials . $surname;
        };

        $studentDeptIds = $studentInfo->pluck('department_id')->filter()->countBy()->sortDesc();
        $majorDeptId = $studentDeptIds->keys()->first();

        $dekanExcelName = '';
        if ($majorDeptId) {
            try {
                $faculty = \App\Models\Department::where('department_hemis_id', $majorDeptId)->first();
                if ($faculty) {
                    $dekan = \App\Models\Teacher::query()
                        ->whereHas('deanFaculties', fn ($q) => $q->where('dean_faculties.department_hemis_id', $faculty->department_hemis_id))
                        ->whereHas('roles', fn ($q) => $q->where('name', \App\Enums\ProjectRole::DEAN->value))
                        ->where('is_active', true)
                        ->orderByRaw("CASE WHEN lavozim = 'Dekan' THEN 0 ELSE 1 END")
                        ->first();
                    if ($dekan) {
                        $dekanExcelName = $abbrFio($dekan->full_name);
                    }
                }
            } catch (\Throwable $e) {
                $dekanExcelName = '';
            }
        }

        // Kafedra mudiri: o'qituvchi orqali (group->teacher kafedrasidan)
        $kafedraMudiriName = '';
        $teacher = $group->teacher;
        if ($teacher && $teacher->department_hemis_id) {
            try {
                $mudiri = \App\Models\Teacher::query()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'kafedra_mudiri'))
                    ->where('department_hemis_id', $teacher->department_hemis_id)
                    ->where('is_active', true)
                    ->first();
                if (!$mudiri) {
                    $mudiri = \App\Models\Teacher::query()
                        ->where('staff_position', 'LIKE', '%mudiri%')
                        ->where('department_hemis_id', $teacher->department_hemis_id)
                        ->where('is_active', true)
                        ->first();
                }
                if ($mudiri) {
                    $kafedraMudiriName = $abbrFio($mudiri->full_name);
                }
            } catch (\Throwable $e) {
                $kafedraMudiriName = '';
            }
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();
        $cleanGroupName = str_replace(['/', '\\', '*', '?', ':', '[', ']'], '_', $group->name ?? 'Vedomost');
        $sheet->setTitle(mb_substr($cleanGroupName, 0, 31));

        // Header
        $sheet->setCellValue('A4', $facultyName ? $facultyName . ' FAKULTETI' : '');
        $sheet->setCellValue('C8', '         ' . $specialtyName);
        $sheet->setCellValue('N8', $kurs);
        $sheet->setCellValue('Q8', $semesterInYear);
        $sheet->setCellValue('W8', $group->name ?? '');
        $sheet->setCellValue('Y1', "Qayta o'qish vedomosti");
        $sheet->setCellValue('C10', '  ' . ($group->subject_name ?? ''));
        $sheet->setCellValue('U10', $teacherNameAbbr);
        $sheet->setCellValue('A11', "Ma'ruzachi:");
        $sheet->setCellValue('C11', '         ' . $teacherNameAbbr);
        $sheet->setCellValue('E60', $dekanExcelName);
        $sheet->setCellValue('U60', $kafedraMudiriName);

        // Vaznlar
        $sheet->setCellValue('V18', 'Yakuniy ball');
        $sheet->setCellValue('D19', $weightJn);
        $sheet->setCellValue('G19', $weightMt);
        $sheet->setCellValue('J19', $weightOn);
        $sheet->setCellValue('P19', $weightOski);
        $sheet->setCellValue('S19', $weightTest);

        $startRow = 20;
        $maxRow = 49;

        foreach ($applications as $idx => $app) {
            $row = $startRow + $idx;
            if ($row > $maxRow) break;

            $student = $app->group->student ?? null;
            $hemisId = $app->student_hemis_id;

            // Joriy = kunlik baholar o'rtachasi
            $rowGrades = collect($gradesMap[$app->id] ?? [])
                ->map(fn ($g) => $g->grade)
                ->filter(fn ($v) => $v !== null);
            $jnVal = $rowGrades->isNotEmpty() ? (int) round($rowGrades->avg()) : 0;

            // Mustaqil ta'lim
            $sub = $mustaqilMap[$app->id] ?? null;
            $mtVal = $sub && $sub->grade !== null ? (int) round((float) $sub->grade) : 0;

            // OSKI / TEST
            $oskiVal = $app->oske_score !== null ? (int) round((float) $app->oske_score) : 0;
            $testVal = $app->test_score !== null ? (int) round((float) $app->test_score) : 0;
            $onVal = 0;

            $sheet->setCellValue('B' . $row, $student?->full_name ?? '—');
            $sheet->setCellValueExplicit('C' . $row, (string) ($student?->student_id_number ?? $hemisId), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $sheet->setCellValue('D' . $row, $jnVal);
            $sheet->setCellValue('G' . $row, $mtVal);
            if ($weightOski > 0) $sheet->setCellValue('P' . $row, $oskiVal);
            if ($weightTest > 0) $sheet->setCellValue('S' . $row, $testVal);

            // Komponent ballari
            $eBall = $jnVal   >= 60 ? round($jnVal   * $weightJn   / 100, 1) : 0;
            $hBall = $mtVal   >= 60 ? round($mtVal   * $weightMt   / 100, 1) : 0;
            $kBall = $onVal   >= 60 ? round($onVal   * $weightOn   / 100, 1) : 0;
            $qBall = ($weightOski > 0 && $oskiVal >= 60) ? round($oskiVal * $weightOski / 100, 1) : 0;
            $tBall = ($weightTest > 0 && $testVal >= 60) ? round($testVal * $weightTest / 100, 1) : 0;

            // Yakuniy ball
            if ($jnVal === 0 && $mtVal === 0) {
                $v = '';
            } elseif (($weightJn > 0 && $jnVal < 60)
                   || ($weightMt > 0 && $mtVal < 60)
                   || ($weightOski > 0 && $oskiVal < 60)
                   || ($weightTest > 0 && $testVal < 60)) {
                $v = 0;
            } else {
                $v = round($eBall + $hBall + $kBall + $qBall + $tBall, 1);
                if ($v > 0) $v = (int) floor($v + 0.5);
            }

            $w = '';
            $y = '';
            if (is_numeric($v)) {
                if ($v >= 90)      { $w = 'A';  $y = "a\u{02BC}lo"; }
                elseif ($v >= 85)  { $w = 'B+'; $y = 'yaxshi'; }
                elseif ($v >= 70)  { $w = 'B';  $y = 'yaxshi'; }
                elseif ($v >= 60)  { $w = 'C';  $y = "o\u{02BB}rta"; }
                elseif ($v > 0)    { $w = 'F';  $y = 'qon-siz'; }
                elseif ($v === 0)  { $w = 'F';  $y = 'qon-siz'; }
            }

            $sheet->setCellValue('E' . $row, $eBall);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('0.0');
            $sheet->setCellValue('H' . $row, $hBall);
            $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('0.0');
            if ($weightOski > 0) {
                $sheet->setCellValue('Q' . $row, $qBall);
                $sheet->getStyle('Q' . $row)->getNumberFormat()->setFormatCode('0.0');
            }
            if ($weightTest > 0) {
                $sheet->setCellValue('T' . $row, $tBall);
                $sheet->getStyle('T' . $row)->getNumberFormat()->setFormatCode('0.0');
            }
            $sheet->setCellValue('V' . $row, $v);
            $sheet->setCellValue('W' . $row, $w);
            $sheet->setCellValue('Y' . $row, $y);
        }

        // Saqlash
        $tempDir = storage_path('app/public/retake/vedomosts');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $cleanSubject = str_replace(['/', '\\', ' '], '_', $group->subject_name ?: 'fan');
        $cleanGroup = str_replace(['/', '\\', ' '], '_', $group->name ?: 'guruh');
        $fileName = sprintf('YN_qaydnoma_%s_%s.xlsx', $cleanGroup, $cleanSubject);
        $relPath = "retake/vedomosts/{$fileName}";
        $absPath = $tempDir . '/' . $fileName;

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($absPath);
        $spreadsheet->disconnectWorksheets();

        return ['path' => $absPath, 'filename' => $fileName, 'relPath' => $relPath];
    }

    /**
     * HEMIS dan kelgan OSKE (training_type_code=101) va Test (102) baholarini
     * student_grades jadvalidan o'qib, retake_applications.oske_score/test_score
     * ga yozish. Mavjud admin/journal logikasiga moslashtirilgan.
     *
     * Filtrlar:
     *  - subject_id retake group bilan mos
     *  - student_hemis_id retake guruhdagi har bir talaba
     *  - lesson_date >= group->oske_date / test_date (qayta o'qish davridagi natija)
     *
     * @return array{fetched_oske:int, fetched_test:int, missing:int}
     */
    public function fetchOskeTestResults(RetakeGroup $group): array
    {
        $applications = $this->applications($group);
        if ($applications->isEmpty()) {
            return ['fetched_oske' => 0, 'fetched_test' => 0, 'missing' => 0];
        }

        $hemisIds = $applications->pluck('student_hemis_id')->filter()->unique()->values();

        $needsOske = in_array($group->assessment_type, ['oske', 'oske_test'], true);
        $needsTest = in_array($group->assessment_type, ['test', 'oske_test'], true);

        $oskeMap = collect();
        $testMap = collect();

        if ($needsOske) {
            $oskeQuery = \Illuminate\Support\Facades\DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $hemisIds)
                ->where('subject_id', $group->subject_id)
                ->where('training_type_code', 101);
            if ($group->oske_date) {
                $oskeQuery->where('lesson_date', '>=', $group->oske_date->format('Y-m-d'));
            }
            $oskeMap = $oskeQuery
                ->select('student_hemis_id', \Illuminate\Support\Facades\DB::raw('ROUND(AVG(grade)) as avg_grade'))
                ->groupBy('student_hemis_id')
                ->pluck('avg_grade', 'student_hemis_id');
        }

        if ($needsTest) {
            $testQuery = \Illuminate\Support\Facades\DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $hemisIds)
                ->where('subject_id', $group->subject_id)
                ->where('training_type_code', 102);
            if ($group->test_date) {
                $testQuery->where('lesson_date', '>=', $group->test_date->format('Y-m-d'));
            }
            $testMap = $testQuery
                ->select('student_hemis_id', \Illuminate\Support\Facades\DB::raw('ROUND(AVG(grade)) as avg_grade'))
                ->groupBy('student_hemis_id')
                ->pluck('avg_grade', 'student_hemis_id');
        }

        $fetchedOske = 0;
        $fetchedTest = 0;
        $missing = 0;

        foreach ($applications as $app) {
            $update = [];
            if ($needsOske) {
                $score = $oskeMap->get($app->student_hemis_id);
                if ($score !== null) {
                    $update['oske_score'] = $score;
                    $fetchedOske++;
                } elseif ($app->oske_score === null) {
                    $missing++;
                }
            }
            if ($needsTest) {
                $score = $testMap->get($app->student_hemis_id);
                if ($score !== null) {
                    $update['test_score'] = $score;
                    $fetchedTest++;
                } elseif ($app->test_score === null) {
                    $missing++;
                }
            }
            if (!empty($update)) {
                $app->update($update);
            }
        }

        return [
            'fetched_oske' => $fetchedOske,
            'fetched_test' => $fetchedTest,
            'missing' => $missing,
        ];
    }
}
