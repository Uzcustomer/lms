<?php

namespace App\Services;

use App\Models\LessonOpening;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * O'qituvchining baho qo'yilmay qolgan darslari — dashboard popupi uchun.
 *
 * Jurnaldagi "o'tkazib yuborilgan kun" qoidasi bilan bir xil
 * (JournalController::showJournal): o'tgan kun (bugun va kelajak emas),
 * amaliy dars (ma'ruza, MT, ON, OSKI, test emas) va o'sha kuni kamida
 * bitta juftlikda guruhdagi birorta talabaga ham baho yoki NB qo'yilmagan.
 * Kun ikki juftlikdan iborat bo'lib, birida baho bor, ikkinchisida yo'q
 * bo'lsa ham kun ochilishi kerak. Bunday kunga o'qituvchi faqat dars ochish
 * so'rovi orqali baho qo'ya oladi.
 *
 * Faqat shu o'qituvchi nomiga jadvalda qo'yilgan darslar, joriy semestr
 * boshidan (LessonOpening::periodStart) kechagacha. Allaqachon so'rov
 * yuborilgan (rad etilmagan) kunlar chiqarilmaydi; rad etilgani qayta
 * yuborish mumkinligi uchun belgi bilan qoladi.
 *
 * Xuddi shu qoida registrator ofisining Excel hisobotida ham ishlaydi
 * (LessonOpeningTeacherReport): u barcha o'qituvchilar uchun pastSlots() va
 * markedPairs() ni chaqiradi, shuning uchun popup va hisobot bir xil javob beradi.
 */
class TeacherMissedLessons
{
    /** Jurnaldagi $excludedTrainingTypes bilan bir xil */
    private const EXCLUDED_TYPE_NAMES = ["Ma'ruza", "Mustaqil ta'lim", 'Oraliq nazorat', 'Oski', 'Yakuniy test', 'Quiz test'];

    /** @return Collection<int, array> eng yangi sana birinchi */
    public function forTeacher(Teacher $teacher): Collection
    {
        if (empty($teacher->hemis_id)) {
            return collect();
        }

        $today = now('Asia/Tashkent')->toDateString();
        $from = LessonOpening::periodStart()->toDateString();

        // 1. O'qituvchining o'tgan amaliy dars juftliklari
        //    (guruh + fan + semestr + sana + juftlik)
        $slots = $this->pastSlots($from, $today, $teacher->hemis_id);

        if ($slots->isEmpty()) {
            return collect();
        }

        $groupIds = $slots->pluck('group_id')->unique()->values()->all();
        $subjectIds = $slots->pluck('subject_id')->unique()->values()->all();

        // Faol talabasi yo'q guruh uchun "baho qo'yilmagan" ma'nosiz
        $groupsWithStudents = $this->groupsWithActiveStudents($groupIds);

        // 2. Qaysi juftliklarda kamida bitta baho yoki NB bor
        $marked = $this->markedPairs($groupIds, $subjectIds, $from, $today);

        // 3. Allaqachon so'rov yuborilgan kunlar: rad etilmaganlari chiqariladi
        $openings = LessonOpening::query()
            ->whereIn('group_hemis_id', $groupIds)
            ->whereIn('subject_id', $subjectIds)
            ->whereDate('lesson_date', '>=', $from)
            ->get(['group_hemis_id', 'subject_id', 'semester_code', 'lesson_date', 'status'])
            ->mapWithKeys(fn ($o) => [
                $this->key($o->group_hemis_id, $o->subject_id, $o->semester_code, $o->lesson_date?->format('Y-m-d')) => $o->status,
            ]);

        // Juftlik hisobga olinmagan bo'lsa kun ro'yxatga tushadi; so'rov esa
        // kunga yuboriladi, shuning uchun bir kun bir marta ko'rinadi
        return $slots
            ->filter(function ($slot) use ($marked, $openings, $groupsWithStudents) {
                $key = $this->key($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day);
                $pairKey = $this->pairKey($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day, $slot->lesson_pair_code);
                $status = $openings[$key] ?? null;

                return ! isset($marked[$pairKey])
                    && isset($groupsWithStudents[(string) $slot->group_id])
                    && ($status === null || $status === LessonOpening::STATUS_REJECTED);
            })
            ->unique(fn ($slot) => $this->key($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day))
            ->map(function ($slot) use ($openings) {
                $key = $this->key($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day);

                return [
                    'group_name' => $slot->group_name,
                    'subject_name' => $slot->subject_name,
                    'lesson_date' => $slot->lesson_day,
                    'rejected' => ($openings[$key] ?? null) === LessonOpening::STATUS_REJECTED,
                    // Jurnal ochilishi bilan shu sana uchun so'rov oynasi ochiladi
                    'url' => route('admin.journal.show', [$slot->group_db_id, $slot->subject_id, $slot->semester_code])
                        .'?open_lesson='.$slot->lesson_day,
                ];
            })
            ->sortByDesc('lesson_date')
            ->values();
    }

    /**
     * Davr boshidan kechagacha o'tgan amaliy dars juftliklari: guruh + fan +
     * semestr + sana + juftlik, har biri o'qituvchisi bilan. $employeeHemisId
     * berilsa — faqat shu o'qituvchining, aks holda jadvaldagi barchasiniki
     * (registrator hisoboti uchun).
     *
     * @return Collection<int, object>
     */
    public function pastSlots(string $from, string $today, string|int|null $employeeHemisId = null): Collection
    {
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        return DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->when(
                $employeeHemisId !== null,
                fn ($query) => $query->where('sch.employee_id', $employeeHemisId),
                fn ($query) => $query->whereNotNull('sch.employee_id')
            )
            ->where('sch.education_year_current', true)
            ->whereNull('sch.deleted_at')
            ->whereNotNull('sch.lesson_date')
            ->whereNotIn('sch.training_type_name', self::EXCLUDED_TYPE_NAMES)
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            // Indeksdan foydalanish uchun DATE() emas, sana chegaralari bilan
            ->where('sch.lesson_date', '>=', $from.' 00:00:00')
            ->where('sch.lesson_date', '<', $today.' 00:00:00')
            ->groupBy('g.id', 'g.name', 'sch.employee_id', 'sch.group_id', 'sch.subject_id', 'sch.semester_code', DB::raw('DATE(sch.lesson_date)'), 'sch.lesson_pair_code')
            ->select(
                'g.id as group_db_id',
                'g.name as group_name',
                'sch.employee_id',
                DB::raw('MAX(sch.employee_name) as employee_name'),
                'sch.group_id',
                'sch.subject_id',
                'sch.semester_code',
                DB::raw('MAX(sch.subject_name) as subject_name'),
                DB::raw('DATE(sch.lesson_date) as lesson_day'),
                'sch.lesson_pair_code',
                DB::raw('MAX(sch.lesson_pair_name) as lesson_pair_name'),
                DB::raw('MAX(sch.lesson_pair_start_time) as lesson_pair_start_time'),
                DB::raw('MAX(sch.lesson_pair_end_time) as lesson_pair_end_time')
            )
            ->get();
    }

    /**
     * Faol (O'qimoqda) talabasi bor guruhlar: [group_id => index].
     * $bachelorOnly — faqat bakalavr talabasi bor guruhlar (hisobot uchun).
     *
     * @return array<string, int>
     */
    public function groupsWithActiveStudents(array $groupIds, bool $bachelorOnly = false): array
    {
        return DB::table('students')
            ->whereIn('group_id', $groupIds)
            ->where('student_status_code', 11)
            // Faqat bakalavr: hisobot magistr/ordinatura guruhlarini chiqarmaydi.
            // LMS bakalavrni ikki xil belgilaydi — education_type_code = '11'
            // (rasmiy kod), yoki education_type_name ichida "bakalavr"/"bakalavriat".
            // Ikkalasini ham qamraymiz (nomi bo'sh yoki boshqa yozuvda bo'lsa ham).
            ->when($bachelorOnly, fn ($q) => $q->where(function ($w) {
                $w->where('education_type_code', '11')
                    ->orWhereRaw('LOWER(education_type_name) LIKE ?', ['%bakalavr%']);
            }))
            ->distinct()
            ->pluck('group_id')
            ->map(fn ($id) => (string) $id)
            ->flip()
            ->all();
    }

    /**
     * Kamida bitta talabaga baho yoki NB qo'yilgan juftliklar:
     * [pairKey() => true]. Jurnaldagi "hisobga olingan juftlik" qoidasi.
     *
     * @return array<string, true>
     */
    public function markedPairs(array $groupIds, array $subjectIds, string $from, string $today): array
    {
        $marked = [];

        DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereIn('st.group_id', $groupIds)
            ->whereIn('sg.subject_id', $subjectIds)
            ->whereNull('sg.deleted_at')
            ->whereNotNull('sg.lesson_date')
            ->whereNotIn('sg.training_type_code', [99, 100, 101, 102, 103])
            ->where('sg.lesson_date', '>=', $from.' 00:00:00')
            ->where('sg.lesson_date', '<', $today.' 00:00:00')
            ->select('sg.id as id', 'st.group_id', 'sg.subject_id', 'sg.semester_code', 'sg.lesson_pair_code', 'sg.grade', 'sg.retake_grade', 'sg.status', 'sg.reason', DB::raw('DATE(sg.lesson_date) as lesson_day'))
            // Butun o'qituvchilar uchun qatorlar ko'p: offset emas, id bo'yicha bo'laklaymiz
            ->chunkById(2000, function ($rows) use (&$marked) {
                foreach ($rows as $row) {
                    if ($row->reason === 'absent' || $this->effectiveGrade($row) !== null) {
                        $marked[$this->pairKey($row->group_id, $row->subject_id, $row->semester_code, $row->lesson_day, $row->lesson_pair_code)] = true;
                    }
                }
            }, 'sg.id', 'id');

        return $marked;
    }

    /** Kun kaliti: guruh | fan | semestr | sana */
    public function key($groupId, $subjectId, $semesterCode, ?string $day): string
    {
        return $groupId.'|'.$subjectId.'|'.$semesterCode.'|'.$day;
    }

    /** Juftlik kaliti: kun kaliti | juftlik kodi */
    public function pairKey($groupId, $subjectId, $semesterCode, ?string $day, ?string $pair): string
    {
        return $this->key($groupId, $subjectId, $semesterCode, $day).'|'.$pair;
    }

    /**
     * Jurnaldagi $getEffectiveGrade bilan bir xil: katakda ko'rinadigan baho.
     * null — katak bo'sh (NB alohida, reason = absent orqali tekshiriladi).
     */
    private function effectiveGrade(object $row)
    {
        if ($row->grade !== null && (float) $row->grade < 60 && $row->retake_grade !== null) {
            return $row->retake_grade;
        }
        if ($row->status === 'pending' && $row->reason === 'low_grade' && $row->grade !== null) {
            return $row->grade;
        }
        if ($row->status === 'pending') {
            return null;
        }
        if ($row->reason === 'absent' && $row->grade === null) {
            return $row->retake_grade;
        }
        if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
            return null;
        }
        if ($row->status === 'recorded' || $row->status === 'closed') {
            return $row->grade;
        }

        return $row->retake_grade;
    }
}
