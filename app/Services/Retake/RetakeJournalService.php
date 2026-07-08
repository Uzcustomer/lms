<?php

namespace App\Services\Retake;

use App\Models\HemisQuizResult;
use App\Models\RetakeApplication;
use App\Models\RetakeGrade;
use App\Models\RetakeGroup;
use App\Models\RetakeMustaqilSubmission;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\VedomostGradeCalculator;
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
     * Test markaziga yuborilgan talabalar ro'yxati.
     */
    public function sentApplications(RetakeGroup $group): Collection
    {
        $sent = RetakeApplication::query()
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('sent_to_test_markazi_at')
            ->with(['group.student'])
            ->orderBy('id')
            ->get();

        if ($sent->isNotEmpty()) {
            return $sent;
        }

        if ($group->sent_to_test_markazi_at) {
            return $this->applications($group);
        }

        return $sent;
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
     *
     * YAGONA mezon — tugash sanasi (end_date) hali tugamaganmi (bugun <= end_date).
     * Qulf (is_locked / yakuniy qilingan) yoki avto-cron qo'yib qo'ygan
     * `completed` status BLOKLAMAYDI — agar muddat hali amal qilsa, baho qo'yish
     * va mustaqil ta'lim ochiq turadi. Muddat uzaytirilsa (end_date kelajakka
     * surilsa) — avtomatik qayta ochiladi.
     */
    public function isEditable(RetakeGroup $group): bool
    {
        if (!$group->start_date || !$group->end_date) {
            return false;
        }
        return $group->end_date->gte(Carbon::today());
    }

    /**
     * Bitta katakka baho qo'yish/tahrirlash.
     */
    /**
     * Talabaning yagona Joriy nazorat (JN) bahosini saqlash.
     * Bitta talaba = bitta JN bahosi (kunlik jadval emas).
     * end_date'gacha qo'yish/tahrirlash mumkin (isEditable orqali).
     */
    public function saveJoriyScore(
        RetakeGroup $group,
        int $applicationId,
        ?float $score,
        Teacher $actor,
        bool $isAdmin = false,
    ): RetakeApplication {
        if (!$isAdmin && !$this->isEditable($group)) {
            throw ValidationException::withMessages([
                'group' => "Bu guruh muddati tugagan yoki qulflangan, baho qo'yib bo'lmaydi",
            ]);
        }

        $app = RetakeApplication::where('id', $applicationId)
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->first();
        if (!$app) {
            throw ValidationException::withMessages([
                'application_id' => 'Ariza guruhga tegishli emas yoki tasdiqlanmagan',
            ]);
        }

        if ($score !== null && ($score < 0 || $score > 100)) {
            throw ValidationException::withMessages([
                'score' => 'Baho 0 dan 100 gacha bo\'lishi kerak',
            ]);
        }

        $app->update([
            'joriy_score' => $score,
            'joriy_graded_by_name' => $actor->full_name,
            'joriy_graded_at' => now(),
        ]);

        return $app->refresh();
    }

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
     *
     * `$applicationId` ixtiyoriy — agar berilgan bo'lsa, aynan shu ariza
     * uchun yuklanadi (bitta guruhda talabaning bir nechta arizasi bo'lsa
     * kerak). Berilmasa, talabaning shu guruhdagi birinchi arizasi olinadi
     * (orqaga moslik).
     */
    public function submitMustaqil(
        RetakeGroup $group,
        Student $student,
        UploadedFile $file,
        ?string $comment = null,
        ?int $applicationId = null,
    ): RetakeMustaqilSubmission {
        // Talaba shu guruhda? (ixtiyoriy applicationId orqali aniqlanadi)
        $query = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED);
        if ($applicationId !== null) {
            $query->where('id', $applicationId);
        }
        $app = $query->first();
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

        // Mavjud topshiriq — qayta yuklash chegaralarini tekshiramiz.
        $existing = RetakeMustaqilSubmission::where('retake_group_id', $group->id)
            ->where('application_id', $app->id)
            ->first();

        $attemptColumnExists = $this->mustaqilAttemptColumnExists();

        if ($existing) {
            // 60+ baho olgan bo'lsa — o'tgan, qayta yuklash shart emas
            if ($existing->grade !== null
                && (float) $existing->grade >= RetakeMustaqilSubmission::PASS_GRADE) {
                throw ValidationException::withMessages([
                    'file' => 'Siz mustaqil ta\'limdan o\'tdingiz (60+ baho) — qayta yuklash shart emas',
                ]);
            }
            // 3 marta urinish tugagan bo'lsa — boshqa yuklab bo'lmaydi
            if ($attemptColumnExists
                && (int) $existing->attempt_count >= RetakeMustaqilSubmission::MAX_ATTEMPTS) {
                throw ValidationException::withMessages([
                    'file' => 'Mustaqil ta\'lim uchun ' . RetakeMustaqilSubmission::MAX_ATTEMPTS
                        . ' marta urinish imkoni tugagan',
                ]);
            }
        }

        // Eski faylni o'chirish (qayta yuklayotgan bo'lsa)
        if ($existing && $existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $path = $file->store("retake/mustaqil/{$group->id}", 'public');

        $values = [
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
        ];
        if ($attemptColumnExists) {
            $values['attempt_count'] = ((int) ($existing->attempt_count ?? 0)) + 1;
        }

        return RetakeMustaqilSubmission::updateOrCreate(
            [
                'retake_group_id' => $group->id,
                'application_id' => $app->id,
            ],
            $values,
        );
    }

    /**
     * `attempt_count` ustuni mavjudligi (migration ishga tushganmi) — bir
     * martagina tekshiriladi va keshlanadi. Migration hali ishlamagan
     * bo'lsa, urinish cheklovi jim qoladi, 500 xato bermaydi.
     */
    private ?bool $mustaqilAttemptColumn = null;

    private function mustaqilAttemptColumnExists(): bool
    {
        if ($this->mustaqilAttemptColumn === null) {
            try {
                $this->mustaqilAttemptColumn = \Illuminate\Support\Facades\Schema::hasColumn(
                    'retake_mustaqil_submissions',
                    'attempt_count'
                );
            } catch (\Throwable $e) {
                $this->mustaqilAttemptColumn = false;
            }
        }
        return $this->mustaqilAttemptColumn;
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

        RetakeApplication::query()
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->update([
                'sent_to_test_markazi_at' => now(),
                'sent_to_test_markazi_by' => $actor->id,
            ]);

        return $group->refresh();
    }

    /**
     * Bitta talabani test markaziga yuborish.
     */
    public function sendApplicationToTestMarkazi(
        RetakeGroup $group,
        RetakeApplication $app,
        Teacher $actor,
    ): RetakeApplication {
        if ((int) $app->retake_group_id !== (int) $group->id) {
            throw ValidationException::withMessages([
                'application_id' => 'Ariza bu guruhga tegishli emas',
            ]);
        }

        if ($app->final_status !== RetakeApplication::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'application_id' => 'Faqat tasdiqlangan arizani yuborish mumkin',
            ]);
        }

        if ($app->sent_to_test_markazi_at) {
            throw ValidationException::withMessages([
                'application_id' => 'Bu talaba allaqachon test markaziga yuborilgan',
            ]);
        }

        // Testga yuborish faqat JN >= 60 VA MT >= 60 bo'lganda mumkin.
        $jn = $app->joriy_score;
        $mt = RetakeMustaqilSubmission::query()
            ->where('retake_group_id', $group->id)
            ->where('application_id', $app->id)
            ->value('grade');
        if ($jn === null || (float) $jn < 60 || $mt === null || (float) $mt < 60) {
            throw ValidationException::withMessages([
                'application_id' => "JN va MT 60 dan kam — testga yuborib bo'lmaydi",
            ]);
        }

        if (!$group->sent_to_test_markazi_at) {
            $group->update([
                'sent_to_test_markazi_at' => now(),
                'sent_to_test_markazi_by' => $actor->id,
            ]);
        }

        $app->update([
            'sent_to_test_markazi_at' => now(),
            'sent_to_test_markazi_by' => $actor->id,
        ]);

        return $app->refresh();
    }

    /**
     * Bitta talabani test markazidan qaytarish.
     */
    public function returnApplicationFromTestMarkazi(
        RetakeGroup $group,
        RetakeApplication $app,
    ): RetakeApplication {
        if ((int) $app->retake_group_id !== (int) $group->id) {
            throw ValidationException::withMessages([
                'application_id' => 'Ariza bu guruhga tegishli emas',
            ]);
        }

        $app->update([
            'sent_to_test_markazi_at' => null,
            'sent_to_test_markazi_by' => null,
        ]);

        $hasSentStudents = RetakeApplication::query()
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('sent_to_test_markazi_at')
            ->exists();

        if (!$hasSentStudents) {
            $group->update([
                'sent_to_test_markazi_at' => null,
                'sent_to_test_markazi_by' => null,
            ]);
        }

        return $app->refresh();
    }

    /**
     * Test markazi OSKE va TEST natijalarini yozadi.
     * Yakuniy bahoni qayta hisoblaymiz.
     */
    public function saveOskeTestScore(
        RetakeApplication $app,
        ?float $oskeScore,
        ?float $testScore,
        ?Teacher $actor = null,
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
            case 'sinov':
            case 'sinov_fan':
                // Sinov (test) — jurnal/test-markazi standart taqsimoti bilan bir xil.
                return ['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 0,  'test' => 30];
            default:
                return ['jn' => 70, 'mt' => 30, 'on' => 0, 'oski' => 0,  'test' => 0];
        }
    }

    /**
     * Test markazi jurnali uchun bitta talabaning YAKUNIY natijasini hisoblaydi —
     * vedomost tekshirish (VedomostGradeCalculator) bilan AYNAN bir xil vazn va
     * yaxlitlash logikasi.
     *
     * Vaznlar:
     *  - JN = 50, MT = 20 (doim).
     *  - OSKE va TEST ikkalasi ham bo'lsa: OSKI = 15, Test = 15.
     *  - Faqat bittasi (OSKE yoki TEST/Sinov) bo'lsa: o'sha nazorat = 30.
     *
     * Holatlar:
     *  - JN yoki MT qo'yilmagan            → 'no_teacher_grade' (o'qituvchi bahosini qo'ymagan)
     *  - Yopilish shakliga ko'ra OSKE/TEST qo'yilmagan → 'absent' (imtihonga kelmagan)
     *  - Istalgan bosqich < 60             → 'failed' (yiqildi)
     *  - Aks holda                         → 'passed', value = yaxlitlangan ozlashtirish
     *
     * @return array{status:string, value:?int, baho:string}
     */
    public function testMarkaziFinalResult($jn, $mt, $oske, $test, ?string $assessmentType, ?string $levelCode = null): array
    {
        $needsOske = in_array($assessmentType, ['oske', 'oske_test'], true);
        $needsTest = in_array($assessmentType, ['test', 'oske_test', 'sinov', 'sinov_fan'], true);

        $jnVal   = ($jn   !== null && $jn   !== '') ? (float) $jn   : null;
        $mtVal   = ($mt   !== null && $mt   !== '') ? (float) $mt   : null;
        $oskeVal = ($oske !== null && $oske !== '') ? (float) $oske : null;
        $testVal = ($test !== null && $test !== '') ? (float) $test : null;

        // 1) O'qituvchi JN yoki MT bahosini qo'ymagan.
        if ($jnVal === null || $mtVal === null) {
            return ['status' => 'no_teacher_grade', 'value' => null, 'baho' => ''];
        }

        // 2) JN yoki MT 60 dan past — talaba testga umuman o'tmaydi → yiqildi.
        if ($jnVal < 60 || $mtVal < 60) {
            return ['status' => 'failed', 'value' => 0, 'baho' => ''];
        }

        // 3) Yopilish shakliga ko'ra kerakli imtihon (OSKE/TEST) natijasi yo'q.
        if (($needsOske && $oskeVal === null) || ($needsTest && $testVal === null)) {
            return ['status' => 'absent', 'value' => null, 'baho' => ''];
        }

        // 4) Imtihon (OSKE/TEST) bosqichi 60 dan past — yiqildi.
        if (($needsOske && $oskeVal < 60) || ($needsTest && $testVal < 60)) {
            return ['status' => 'failed', 'value' => 0, 'baho' => ''];
        }

        // 5) Yakuniy natija — vedomost tekshirish vaznlari bilan.
        $weights = ['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 0, 'test' => 0];
        if ($needsOske && $needsTest) {
            $weights['oski'] = 15;
            $weights['test'] = 15;
        } elseif ($needsOske) {
            $weights['oski'] = 30;
        } elseif ($needsTest) {
            $weights['test'] = 30;
        } else {
            // OSKE/TEST ko'zda tutilmagan (nazariy holat) — 30 ni JN/MT taqsimoti qoplaydi.
            $weights['jn'] = 70;
            $weights['mt'] = 30;
        }

        $computed = (new VedomostGradeCalculator())->compute(
            $jnVal,
            $mtVal,
            null,
            $needsOske ? $oskeVal : null,
            $needsTest ? $testVal : null,
            $levelCode,
            $weights,
        );

        $value = $computed['ozlashtirish'];

        return [
            'status' => 'passed',
            'value' => is_numeric($value) ? (int) $value : null,
            'baho' => $computed['baho'] ?? '',
        ];
    }

    /**
     * @param  array{jn:int,mt:int,on:int,oski:int,test:int}|null  $weights
     */
    /**
     * Guruhdagi tasdiqlangan arizalarning unikal semestr raqamlari (o'sish bo'yicha).
     * Bittadan ortiq bo'lsa — har semestr uchun alohida vedomost shakllantiriladi.
     *
     * @return array<int>
     */
    public function vedomostSemesterNumbers(RetakeGroup $group): array
    {
        return $this->applications($group)
            ->map(fn ($a) => preg_match('/(\d+)/', (string) $a->semester_name, $m) ? (int) $m[1] : null)
            ->filter(fn ($n) => $n !== null)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function buildVedomostExcel(RetakeGroup $group, ?array $weights = null, ?int $semesterNumber = null): array
    {
        $templatePath = public_path('templates/yn_qaydnoma (1).xlsx');
        if (!file_exists($templatePath)) {
            throw ValidationException::withMessages([
                'template' => 'Vedomost shabloni topilmadi: templates/yn_qaydnoma (1).xlsx',
            ]);
        }

        $applications = $this->applications($group);

        // Semestr bo'yicha alohida vedomost — faqat shu semestrdagi arizalar.
        if ($semesterNumber !== null) {
            $applications = $applications->filter(function ($a) use ($semesterNumber) {
                $num = preg_match('/(\d+)/', (string) $a->semester_name, $m) ? (int) $m[1] : null;
                return $num === $semesterNumber;
            })->values();

            if ($applications->isEmpty()) {
                throw ValidationException::withMessages([
                    'semester' => "Bu guruhda {$semesterNumber}-semestr bo'yicha talaba yo'q",
                ]);
            }
        }

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
        // Semestr — agar alohida-semestr vedomost bo'lsa, aynan shu semestr;
        // aks holda arizalardagi ko'pchilik semestr.
        $semesterName = $semesterNumber !== null
            ? ($applications->first()->semester_name ?: ($semesterNumber . '-semestr'))
            : $pickMostCommon($studentInfo, 'semester_name');
        $kurs = preg_match('/(\d+)/', $levelName, $m) ? $m[1] : '';
        $semesterInYear = $semesterNumber !== null
            ? $semesterNumber
            : (preg_match('/(\d+)/', $semesterName, $m) ? (int) $m[1] : '');

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

            // Joriy = bitta saqlangan JN bahosi (yagona, kunlik jadval emas)
            $jnVal = $app->joriy_score !== null ? (int) round((float) $app->joriy_score) : 0;

            // Mustaqil ta'lim
            $sub = $mustaqilMap[$app->id] ?? null;
            $mtVal = $sub && $sub->grade !== null ? (int) round((float) $sub->grade) : 0;

            // OSKI / TEST
            $oskiVal = $app->oske_score !== null ? (int) round((float) $app->oske_score) : 0;
            // Sinov fanlarda Test komponenti = JN (jadval/eksport bilan bir xil).
            $testSource = in_array($group->assessment_type, ['sinov', 'sinov_fan'], true)
                ? $app->joriy_score
                : $app->test_score;
            $testVal = $testSource !== null ? (int) round((float) $testSource) : 0;
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
        $semesterSuffix = $semesterNumber !== null ? "_S{$semesterNumber}" : '';
        $fileName = sprintf('YN_qaydnoma_%s_%s%s.xlsx', $cleanGroup, $cleanSubject, $semesterSuffix);
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

        // DIQQAT: bu metod (eski "Natijalarni tortish" yo'li) FASL/sessiya
        // filtri va sana chegarasi (test_date) ixtiyoriy bo'lgani uchun sinov
        // guruhlarda xavfli — test_date bo'sh bo'lsa, shu talaba+fandagi
        // BARCHA (istalgan semestr/sessiya) 102-baholari o'rtachalanib yoziladi.
        // Sinov uchun sessiya-xavfsiz yo'l — fetchRetakeResultsFromQuiz
        // ("Diagnostika orqali yuklash", test markazi sahifasi).
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

    /**
     * Diagnostika orqali — qayta o'qish quiz natijalarini to'g'ridan-to'g'ri
     * `hemis_quiz_results` (Moodle sync manbai) dan o'qib,
     * retake_applications.oske_score / test_score ga yozadi.
     *
     * `fetchOskeTestResults` dan asosiy farqi: natija FASL (sessiya kodi)
     * bo'yicha QAT'IY filtrlanadi. Quiz nomidagi `Qayta-o'qish-YYYY-YYYY-fasl`
     * tokeni guruh sessiyasiga mos kelmasa — natija RAD ETILADI. Shu bilan
     * qishki sessiya natijasi yozgi jurnalga oqib o'tishi oldi olinadi.
     *
     * @return array{
     *   fetched_oske:int, fetched_test:int, missing:int,
     *   rejected_other_session:int, session_code:string
     * }
     */
    public function fetchRetakeResultsFromQuiz(RetakeGroup $group, Teacher $actor): array
    {
        $sessionCode = $group->sessionCode();
        if ($sessionCode === null) {
            throw ValidationException::withMessages([
                'session' => "Guruh sessiyasi (o'quv yili / fasl) aniqlanmadi — diagnostika orqali yuklab bo'lmaydi. "
                    . "Sessiya nomida YYYY-YYYY va fasl (kuzgi/qishki/yozgi) bo'lishi shart.",
            ]);
        }

        $applications = $this->applications($group);

        $base = [
            'fetched_oske' => 0,
            'fetched_test' => 0,
            'missing' => 0,
            'rejected_other_session' => 0,
            'session_code' => $sessionCode,
        ];

        if ($applications->isEmpty()) {
            return $base;
        }

        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

        $needsOske = in_array($group->assessment_type, ['oske', 'oske_test'], true);
        $needsTest = in_array($group->assessment_type, ['test', 'oske_test', 'sinov', 'sinov_fan'], true);

        $relevantTypes = array_merge(
            $needsOske ? $oskiTypes : [],
            $needsTest ? $testTypes : [],
        );
        if (empty($relevantTypes)) {
            return $base;
        }

        $hemisIds = $applications->pluck('student_hemis_id')
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();

        // Talaba kaliti: hemis_quiz_results.student_id = students.student_id_number
        // (Moodle id), ariza esa student_hemis_id (LMS hemis_id) bilan ishlaydi.
        // Shuning uchun hemis_id <-> student_id_number xaritasini quramiz.
        $hemisToSid = [];
        $sidToHemis = [];
        foreach (\App\Models\Student::whereIn('hemis_id', $hemisIds)->get(['hemis_id', 'student_id_number']) as $st) {
            $h = (string) $st->hemis_id;
            $sid = (string) $st->student_id_number;
            if ($sid !== '') {
                $hemisToSid[$h] = $sid;
                $sidToHemis[$sid] = $h;
            }
        }
        $quizStudentIds = array_values(array_unique($hemisToSid));
        if (empty($quizStudentIds)) {
            return $base;
        }

        // Fan id uchta xil id-makonda bo'lishi mumkin (HEMIS 171 / ariza 78 /
        // guruh 142), nomi esa bir xil — shuning uchun fan_id YOKI NOM bo'yicha
        // moslaymiz (matchRetakeApp kabi).
        $groupSubjectNorm = $this->normSubjectName($group->subject_name);

        $rows = HemisQuizResult::query()
            ->where('is_active', 1)
            ->whereIn('student_id', $quizStudentIds)
            ->whereIn('quiz_type', $relevantTypes)
            ->get(['student_id', 'quiz_type', 'attempt_name', 'shakl', 'grade', 'date_finish', 'fan_id', 'fan_name', 'semester']);

        // Tokensiz qayta o'qish quizlari (nomida yil-fasl yo'q, faqat "Qayta-o'qish")
        // uchun — matchRetakeApp dagi kabi — guruh sessiyasi OCHIQ bo'lsa qabul qilamiz.
        $session = $group->resolveSession();
        $sessionOpen = $session !== null && !$session->is_closed;
        $cutoff = config('retake.tokenless_open_cutoff');

        // [hemis_id][semNum]['oske'|'test'] => eng yuqori baho (faqat shu sessiya).
        // Semestr kaliti: bitta talabada bir xil fandan bir nechta semestr arizasi
        // bo'lsa (3-sem/4-sem), natija to'g'ri semestrga tushishi uchun.
        $best = [];
        $rejected = 0;
        foreach ($rows as $row) {
            // Fan mosligi: id (HEMIS) yoki nom bo'yicha.
            $sameSubject = ((string) $row->fan_id === (string) $group->subject_id)
                || ($groupSubjectNorm !== '' && $this->normSubjectName($row->fan_name) === $groupSubjectNorm);
            if (!$sameSubject) {
                continue;
            }
            // Oddiy (qayta o'qish bo'lmagan) quizlar — rad etiladi.
            if (!RetakeSessionCode::isRetakeQuiz($row->attempt_name, $row->shakl)) {
                $rejected++;
                continue;
            }
            $rowCode = RetakeSessionCode::fromQuizName($row->attempt_name, $row->shakl);
            if ($rowCode !== null) {
                // Token bor — qat'iy fasl/o'quv yili mosligi.
                if ($rowCode !== $sessionCode) {
                    $rejected++;
                    continue;
                }
            } else {
                // Tokensiz qayta o'qish — faqat guruh sessiyasi OCHIQ va urinish
                // cutoff sanasidan keyin bo'lsa (yozgi 2025-2026: 2026-05-07 dan).
                // Undan oldingilari o'tgan fasl (qishki) — joriyga olinmaydi.
                if (!$sessionOpen) {
                    $rejected++;
                    continue;
                }
                if ($cutoff !== null && $row->date_finish !== null
                    && substr((string) $row->date_finish, 0, 10) < $cutoff) {
                    $rejected++;
                    continue;
                }
            }
            if ($row->grade === null) {
                continue;
            }
            $grade = (float) $row->grade;
            $kind = in_array($row->quiz_type, $oskiTypes, true) ? 'oske' : 'test';
            $hid = $sidToHemis[(string) $row->student_id] ?? null; // student_id_number -> hemis_id
            if ($hid === null) {
                continue;
            }
            // Semestr: `semester` maydoni ko'pincha NULL — nomidan ("N-sem") olamiz.
            $semKey = RetakeSessionCode::semesterNumber($row->semester, $row->attempt_name) ?? 0; // 0 = noma'lum
            if (!isset($best[$hid][$semKey][$kind]) || $grade > $best[$hid][$semKey][$kind]) {
                $best[$hid][$semKey][$kind] = $grade;
            }
        }

        // Talaba+semestr bo'yicha kerakli bahoni tanlaydi: aniq semestr mosligi;
        // agar aniq mos yo'q, lekin shu talabada faqat bitta semestr guruhi bo'lsa —
        // o'shani oladi (bir xil talabaning shu guruhdagi yagona arizasi holati).
        $pickBest = function (string $hid, ?int $appSem, string $kind) use ($best) {
            $byHid = $best[$hid] ?? [];
            // 1) Aniq semestr mosligi.
            if ($appSem !== null && isset($byHid[$appSem][$kind])) {
                return $byHid[$appSem][$kind];
            }
            // 2) Semestri belgilanmagan (0) quiz natijasi — istalgan arizaga tegishli.
            if (isset($byHid[0][$kind])) {
                return $byHid[0][$kind];
            }
            // 3) Ariza semestri NOMA'LUM bo'lsa-yu, faqat bitta semestr guruhi bo'lsa —
            //    o'shani ol. Ariza semestri ma'lum bo'lsa, boshqa semestr natijasiga
            //    TUSHMAYMIZ (cross-semester contamination oldini olish).
            if ($appSem === null) {
                $withKind = array_filter($byHid, fn ($b) => isset($b[$kind]));
                if (count($withKind) === 1) {
                    return reset($withKind)[$kind];
                }
            }
            return null;
        };

        $fetchedOske = 0;
        $fetchedTest = 0;
        $missing = 0;

        foreach ($applications as $app) {
            $hid = (string) $app->student_hemis_id;
            $appSem = preg_match('/(\d+)/', (string) $app->semester_name, $am) ? (int) $am[1] : null;
            $oske = $needsOske ? $pickBest($hid, $appSem, 'oske') : null;
            $test = $needsTest ? $pickBest($hid, $appSem, 'test') : null;

            $hasUpdate = false;
            if ($needsOske) {
                if ($oske !== null) {
                    $fetchedOske++;
                    $hasUpdate = true;
                } elseif ($app->oske_score === null) {
                    $missing++;
                }
            }
            if ($needsTest) {
                if ($test !== null) {
                    $fetchedTest++;
                    $hasUpdate = true;
                } elseif ($app->test_score === null) {
                    $missing++;
                }
            }

            if ($hasUpdate) {
                // Yakuniy baho qayta hisoblash logikasi bitta joyda
                // (saveOskeTestScore) saqlanadi. Yangi natija bo'lmagan
                // komponent uchun mavjud qiymat saqlanadi.
                $this->saveOskeTestScore(
                    $app,
                    $oske !== null ? round($oske) : ($app->oske_score !== null ? (float) $app->oske_score : null),
                    $test !== null ? round($test) : ($app->test_score !== null ? (float) $app->test_score : null),
                    $actor,
                );
            }
        }

        return [
            'fetched_oske' => $fetchedOske,
            'fetched_test' => $fetchedTest,
            'missing' => $missing,
            'rejected_other_session' => $rejected,
            'session_code' => $sessionCode,
        ];
    }

    /**
     * Fan nomini taqqoslash uchun normallashtiradi. Vedomost mantig'i bilan
     * bir xil: fan nomidan variant suffiksini ("(a)","(b)","(c)","(1)") kesib
     * o'zak fanni oladi (VedomostMergeService::rootSubjectName), so'ng kichik
     * harf + bo'shliqlarni tartibga keltiradi.
     */
    private function normSubjectName(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }
        $root = app(\App\Services\VedomostMergeService::class)->rootSubjectName($s);

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($root)));
    }
}
