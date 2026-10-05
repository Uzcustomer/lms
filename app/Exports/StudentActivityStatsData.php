<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;

/**
 * "Faollik va mustaqil ta'lim" hisobotining so'rovlari. Har metod bitta
 * varaqning qatorlarini beradi; Excel ko'rinishi StudentActivityStatsSheet da.
 *
 * Oraliq ikkala chetdan yopiq: [$from 00:00:00, $to 23:59:59].
 *
 * Faqat o'qiyotgan talabalar (student_status_code = 11) sanaladi — chetlashgan
 * yoki akademik ta'tildagi talabadan faollik kutilmaydi.
 */
class StudentActivityStatsData
{
    private const ACTIVE_STATUS = '11';

    /** Mustaqil ta'lim bahosi student_grades da shu tur kodi bilan turadi */
    private const MT_TRAINING_TYPE = 99;

    private string $fromTs;

    private string $toTs;

    public function __construct(private string $from, private string $to)
    {
        $this->fromTs = $from.' 00:00:00';
        $this->toTs = $to.' 23:59:59';
    }

    /**
     * Guruh kesimida kirish faolligi.
     *
     * Kirish — activity_logs dagi guard='student', action='login' yozuvi.
     * Mobil ilovadan kirish ham shu jadvalga yoziladi (AuthController).
     *
     * @return list<array<string, mixed>>
     */
    public function loginsByGroup(): array
    {
        // Oraliqdagi kirishlar: talaba => [kirishlar soni, oxirgi kirish]
        $logins = DB::table('activity_logs')
            ->where('guard', 'student')
            ->where('action', 'login')
            ->whereBetween('created_at', [$this->fromTs, $this->toTs])
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'), DB::raw('MAX(created_at) as last_at'))
            ->get()
            ->keyBy('user_id');

        $rows = [];
        DB::table('students as st')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->select('st.id', 'st.group_id', 'g.name as group_name', 'g.department_name', 'st.level_name', 'st.education_type_name')
            ->orderBy('g.department_name')->orderBy('g.name')
            ->cursor()
            ->each(function ($st) use (&$rows, $logins) {
                $key = (string) $st->group_id;
                $rows[$key] ??= [
                    'department' => (string) ($st->department_name ?? ''),
                    'group' => (string) ($st->group_name ?? $st->group_id),
                    'course' => (string) ($st->level_name ?? ''),
                    'education_type' => (string) ($st->education_type_name ?? ''),
                    'students' => 0,
                    'logged_in' => 0,
                    'not_logged_in' => 0,
                    'logins' => 0,
                    'last_login' => null,
                ];
                $rows[$key]['students']++;
                $l = $logins[$st->id] ?? null;
                if ($l) {
                    $rows[$key]['logged_in']++;
                    $rows[$key]['logins'] += (int) $l->cnt;
                    if ($rows[$key]['last_login'] === null || $l->last_at > $rows[$key]['last_login']) {
                        $rows[$key]['last_login'] = $l->last_at;
                    }
                } else {
                    $rows[$key]['not_logged_in']++;
                }
            });

        foreach ($rows as &$r) {
            $r['percent'] = $r['students'] > 0 ? round($r['logged_in'] * 100 / $r['students'], 1) : 0;
        }
        unset($r);

        return array_values($rows);
    }

    /**
     * Oraliqda birorta ham kirmagan faol talabalar — ro'yxat.
     * Umuman hech qachon kirgan-kirmagani ham ko'rsatiladi (oxirgi kirish).
     *
     * @return list<array<string, mixed>>
     */
    public function neverLoggedIn(): array
    {
        $inRange = DB::table('activity_logs')
            ->where('guard', 'student')->where('action', 'login')
            ->whereBetween('created_at', [$this->fromTs, $this->toTs])
            ->distinct()->pluck('user_id')->flip()->all();

        // Umumiy oxirgi kirish (oraliqdan tashqarida bo'lsa ham) — "hech qachon" ni ajratish uchun
        $lastEver = DB::table('activity_logs')
            ->where('guard', 'student')->where('action', 'login')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('MAX(created_at) as last_at'))
            ->pluck('last_at', 'user_id');

        $rows = [];
        DB::table('students as st')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->select('st.id', 'st.full_name', 'st.student_id_number', 'g.name as group_name', 'g.department_name', 'st.level_name', 'st.phone', 'st.telegram_chat_id')
            ->orderBy('g.department_name')->orderBy('g.name')->orderBy('st.full_name')
            ->cursor()
            ->each(function ($st) use (&$rows, $inRange, $lastEver) {
                if (isset($inRange[$st->id])) {
                    return;
                }
                $rows[] = [
                    'department' => (string) ($st->department_name ?? ''),
                    'group' => (string) ($st->group_name ?? ''),
                    'course' => (string) ($st->level_name ?? ''),
                    'student' => (string) $st->full_name,
                    'student_id_number' => (string) ($st->student_id_number ?? ''),
                    'last_login' => $lastEver[$st->id] ?? null,
                    'telegram' => ! empty($st->telegram_chat_id) ? 'Ha' : "Yo'q",
                ];
            });

        return $rows;
    }

    /**
     * Fan kesimida mustaqil ta'lim: oraliqda yuklangan fayllar, shundan
     * baholangani va baholanmagani.
     *
     * "Yuklangan" — independent_submissions.submitted_at oraliqda.
     * "Baholangan" — student_grades da shu independent_id va shu talaba uchun
     * MT bahosi (training_type_code = 99) bor.
     *
     * Fan nomi independents.subject_name dan olinadi: avtomatik yaratilgan
     * topshiriqlarda subject_id to'ldirilmaydi, nom esa har doim bor.
     *
     * @return list<array<string, mixed>>
     */
    public function independentBySubject(): array
    {
        $rows = [];

        DB::table('independent_submissions as s')
            ->join('independents as i', 'i.id', '=', 's.independent_id')
            ->leftJoin('student_grades as sg', function ($j) {
                $j->on('sg.independent_id', '=', 's.independent_id')
                    ->on('sg.student_hemis_id', '=', 's.student_hemis_id')
                    ->where('sg.training_type_code', self::MT_TRAINING_TYPE)
                    ->whereNull('sg.deleted_at')
                    ->whereNotNull('sg.grade');
            })
            ->whereBetween('s.submitted_at', [$this->fromTs, $this->toTs])
            ->groupBy('i.subject_name', 'i.deportment_name')
            ->select(
                'i.subject_name',
                'i.deportment_name',
                DB::raw('COUNT(*) as uploaded'),
                DB::raw('COUNT(sg.id) as graded'),
                DB::raw('COUNT(DISTINCT s.student_hemis_id) as students'),
                DB::raw('COUNT(DISTINCT i.group_hemis_id) as groups_cnt'),
                DB::raw('COUNT(DISTINCT i.teacher_hemis_id) as teachers'),
                DB::raw('MAX(s.submitted_at) as last_upload')
            )
            ->orderBy('i.deportment_name')->orderBy('i.subject_name')
            ->cursor()
            ->each(function ($r) use (&$rows) {
                $uploaded = (int) $r->uploaded;
                $graded = (int) $r->graded;
                $rows[] = [
                    'department' => (string) ($r->deportment_name ?? ''),
                    'subject' => (string) $r->subject_name,
                    'groups' => (int) $r->groups_cnt,
                    'teachers' => (int) $r->teachers,
                    'students' => (int) $r->students,
                    'uploaded' => $uploaded,
                    'graded' => $graded,
                    'ungraded' => $uploaded - $graded,
                    'percent' => $uploaded > 0 ? round($graded * 100 / $uploaded, 1) : 0,
                    'last_upload' => $r->last_upload,
                ];
            });

        return $rows;
    }

    /**
     * Fan kesimida materiallar: oraliqda boshlanadigan (start_date) MT
     * topshiriqlari soni va shundan nechtasiga o'qituvchi fayl biriktirgan.
     *
     * Tizimda alohida "material" jadvali yo'q — o'qituvchi fayli topshiriq
     * qatorining o'zida (independents.file_path) turadi. Shuning uchun
     * "fanda material bor" = kamida bitta topshiriqda fayl bor.
     *
     * @return list<array<string, mixed>>
     */
    public function materialsBySubject(): array
    {
        $rows = [];

        DB::table('independents as i')
            ->whereBetween('i.start_date', [$this->from, $this->to])
            ->groupBy('i.subject_name', 'i.deportment_name')
            ->select(
                'i.subject_name',
                'i.deportment_name',
                DB::raw('COUNT(*) as tasks'),
                DB::raw("SUM(CASE WHEN i.file_path IS NOT NULL AND i.file_path <> '' THEN 1 ELSE 0 END) as with_file"),
                DB::raw('COUNT(DISTINCT i.group_hemis_id) as groups_cnt'),
                DB::raw('COUNT(DISTINCT i.teacher_hemis_id) as teachers')
            )
            ->orderBy('i.deportment_name')->orderBy('i.subject_name')
            ->cursor()
            ->each(function ($r) use (&$rows) {
                $tasks = (int) $r->tasks;
                $withFile = (int) $r->with_file;
                $rows[] = [
                    'department' => (string) ($r->deportment_name ?? ''),
                    'subject' => (string) $r->subject_name,
                    'groups' => (int) $r->groups_cnt,
                    'teachers' => (int) $r->teachers,
                    'tasks' => $tasks,
                    'with_file' => $withFile,
                    'without_file' => $tasks - $withFile,
                    'has_material' => $withFile > 0 ? 'Bor' : "Yo'q",
                ];
            });

        return $rows;
    }
}
