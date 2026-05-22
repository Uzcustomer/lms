<?php

namespace App\Jobs;

use App\Exports\JournalExamGradesExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Jurnal — OSKI/Test baholarini Excelga eksport qilish (background job).
 *
 * Sana oralig'i tanlangan oraliqda kamida bitta OSKI/Test bahosi bo'lgan
 * (talaba, fan) juftliklarini aniqlaydi; so'ngra o'sha juftliklarning
 * BARCHA urinishlari (oraliqdan tashqaridagi 1-urinish ham) ko'rsatiladi —
 * jurnal jadvalidagi qator bilan bir xil to'liq bo'lishi uchun.
 *
 * Baholar jurnal mantig'i bilan AYNAN bir xil olinadi:
 *  - 1-urinish (asosiy ustun): jurnal getEffectiveGrade qoidasi;
 *  - 2/3-urinish va qo'shimcha farmoyish: jurnal fetchAttemptOskiTest qoidasi;
 *  - legacy 103 kodli quiz baholari quiz_type orqali OSKI/Test ga aylanadi;
 *  - bir urinishda bir nechta yozuv bo'lsa — o'rtacha.
 */
class JournalExamGradesExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    private const OSKI_QUIZ_TYPES = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
    private const TEST_QUIZ_TYPES = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

    public function __construct(
        private readonly array $filters,
        private readonly string $exportKey,
    ) {}

    public static function dirPath(): string
    {
        $dir = storage_path('app/private/exports/journal_exam_grades');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function relativeXlsx(string $exportKey): string
    {
        return 'exports/journal_exam_grades/' . md5($exportKey) . '.xlsx';
    }

    public static function pathsFor(string $exportKey): array
    {
        $hash = md5($exportKey);
        $dir = self::dirPath();
        return [
            'xlsx' => $dir . '/' . $hash . '.xlsx',
            'meta' => $dir . '/' . $hash . '.json',
        ];
    }

    private function kursFromSemester(?string $semName, ?string $semCode): ?int
    {
        $semNum = null;
        if (!empty($semName) && preg_match('/(\d+)/', (string) $semName, $m)) {
            $semNum = (int) $m[1];
        } elseif (!empty($semCode) && preg_match('/(\d+)/', (string) $semCode, $m2)) {
            $semNum = (int) $m2[1];
        }
        return $semNum ? (int) ceil($semNum / 2) : null;
    }

    /**
     * Jurnal getEffectiveGrade — 1-urinish (asosiy ustun) uchun.
     */
    private function effMain(object $r): ?float
    {
        if ($r->grade !== null && (float) $r->grade < 60 && $r->retake_grade !== null) {
            return (float) $r->retake_grade;
        }
        if ($r->status === 'pending' && $r->reason === 'low_grade' && $r->grade !== null) {
            return (float) $r->grade;
        }
        if ($r->status === 'pending') {
            return null;
        }
        if ($r->reason === 'absent' && $r->grade === null) {
            return $r->retake_grade !== null ? (float) $r->retake_grade : null;
        }
        if ($r->status === 'closed' && $r->reason === 'teacher_victim'
            && $r->grade !== null && (float) $r->grade == 0.0 && $r->retake_grade === null) {
            return null;
        }
        if ($r->status === 'recorded' || $r->status === 'closed') {
            return $r->grade !== null ? (float) $r->grade : null;
        }
        if ($r->retake_grade !== null) {
            return (float) $r->retake_grade;
        }
        return null;
    }

    /**
     * Jurnal fetchAttemptOskiTest (excludeSababli=false) — 2/3-urinish
     * va qo'shimcha farmoyish ustunlari uchun.
     */
    private function effAttempt(object $r): ?float
    {
        if ($r->status === 'pending' && $r->reason === 'low_grade' && $r->grade !== null) {
            return (float) $r->grade;
        }
        if ($r->status === 'pending') {
            return null;
        }
        if ($r->reason === 'absent' && $r->grade === null) {
            return $r->retake_grade !== null ? (float) $r->retake_grade : null;
        }
        if ($r->status === 'recorded' || $r->status === 'closed') {
            return $r->grade !== null ? (float) $r->grade : null;
        }
        if ($r->retake_grade !== null) {
            return (float) $r->retake_grade;
        }
        return null;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '1024M');

        try {
            $from     = $this->filters['from'];
            $to       = $this->filters['to'];
            $kurslar  = $this->filters['kurslar'] ?? [];
            $facultyHemisIds = $this->filters['faculty_hemis_ids'] ?? [];

            $hasAttemptCol   = Schema::hasColumn('student_grades', 'attempt');
            $hasQoshimchaCol = Schema::hasColumn('student_grades', 'is_qoshimcha');

            // 1) Qamrov — tanlangan sana oralig'ida (imtihon kuni bo'yicha)
            // kamida bitta OSKI/Test/quiz bahosi bo'lgan (talaba, fan) juftliklari.
            $this->updateStatus('running', 'Talabalar aniqlanmoqda...', 5);

            $scopeQ = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
                ->whereNull('sg.deleted_at')
                ->whereIn('sg.training_type_code', [101, 102, 103])
                ->whereBetween('sg.lesson_date', [$from . ' 00:00:00', $to . ' 23:59:59']);
            if (!empty($facultyHemisIds)) {
                $scopeQ->whereIn('g.department_hemis_id', $facultyHemisIds);
            }
            $scopeRows = $scopeQ
                ->select('sg.student_hemis_id', 'sg.subject_id', 'sg.semester_name', 'sg.semester_code', 'sg.education_year_code')
                ->distinct()
                ->get();

            $scopeSet    = [];   // "student|subject" => true
            $studentIds  = [];
            $subjectIds  = [];
            $eduYears    = [];
            foreach ($scopeRows as $sr) {
                $kurs = $this->kursFromSemester($sr->semester_name, $sr->semester_code);
                if (!empty($kurslar) && (!$kurs || !in_array($kurs, $kurslar, true))) {
                    continue;
                }
                $scopeSet[$sr->student_hemis_id . '|' . $sr->subject_id] = true;
                $studentIds[$sr->student_hemis_id] = true;
                $subjectIds[$sr->subject_id] = true;
                if ($sr->education_year_code !== null && $sr->education_year_code !== '') {
                    $eduYears[$sr->education_year_code] = true;
                }
            }
            $studentIds = array_keys($studentIds);
            $subjectIds = array_keys($subjectIds);
            $eduYears   = array_keys($eduYears);
            unset($scopeRows);

            if (empty($studentIds)) {
                $this->storeExcel([], $from, $to);
                $this->updateStatus('done', 'Tanlangan oraliqda baho topilmadi', 100, $this->fileName($from, $to));
                return;
            }

            // 2) Topilgan talabalarning BARCHA OSKI/Test urinishlari (sana
            // cheklovisiz). Eski o'quv yili yozuvlari oqib o'tmasligi uchun
            // joriy o'quv yili (yoki o'quv yili belgilanmagan) bilan cheklanadi.
            $this->updateStatus('running', 'Baholar yuklanmoqda...', 15);

            $select = [
                'sg.student_hemis_id', 'sg.subject_id', 'sg.subject_name',
                'sg.semester_name', 'sg.semester_code', 'sg.training_type_code',
                'sg.grade', 'sg.retake_grade', 'sg.status', 'sg.reason', 'sg.quiz_result_id',
                'sg.lesson_date',
                'st.full_name', 'st.student_id_number', 'st.group_name',
            ];
            if ($hasAttemptCol)   $select[] = 'sg.attempt';
            if ($hasQoshimchaCol) $select[] = 'sg.is_qoshimcha';

            $map = [];
            $chunks = array_chunk($studentIds, 500);
            $totalChunks = count($chunks);
            $ci = 0;

            foreach ($chunks as $chunk) {
                $rows = DB::table('student_grades as sg')
                    ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                    ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
                    ->whereNull('sg.deleted_at')
                    ->whereIn('sg.training_type_code', [101, 102, 103])
                    ->whereIn('sg.student_hemis_id', $chunk)
                    ->whereIn('sg.subject_id', $subjectIds)
                    ->where(function ($q) use ($eduYears) {
                        $q->whereNull('sg.education_year_code');
                        if (!empty($eduYears)) {
                            $q->orWhereIn('sg.education_year_code', $eduYears);
                        }
                    })
                    ->select($select)
                    ->get();

                // Legacy 103 -> OSKI/Test (quiz_type orqali) — chunk bo'yicha.
                $quizTypeMap = [];
                $qids = $rows->where('training_type_code', 103)
                    ->pluck('quiz_result_id')->filter()->unique()->values()->all();
                if (!empty($qids)) {
                    $quizTypeMap = DB::table('hemis_quiz_results')
                        ->whereIn('id', $qids)->pluck('quiz_type', 'id')->all();
                }

                foreach ($rows as $r) {
                    $key = $r->student_hemis_id . '|' . $r->subject_id;
                    if (!isset($scopeSet[$key])) {
                        continue;
                    }

                    $rawTtc = (int) $r->training_type_code;
                    $ttc = $rawTtc;
                    if ($rawTtc === 103) {
                        $qt = $r->quiz_result_id ? ($quizTypeMap[$r->quiz_result_id] ?? null) : null;
                        if (in_array($qt, self::OSKI_QUIZ_TYPES, true)) {
                            $ttc = 101;
                        } elseif (in_array($qt, self::TEST_QUIZ_TYPES, true)) {
                            $ttc = 102;
                        } else {
                            continue;
                        }
                    }
                    if ($ttc !== 101 && $ttc !== 102) {
                        continue;
                    }
                    $type = ($ttc === 101) ? 'oski' : 'test';

                    $attempt = $hasAttemptCol ? (int) ($r->attempt ?? 1) : 1;
                    if ($attempt < 1 || $attempt > 3) {
                        $attempt = 1;
                    }
                    $isQosh = $hasQoshimchaCol && !empty($r->is_qoshimcha);

                    // Jurnalda legacy 103 faqat 1-urinish asosiy ustunda ishtirok etadi.
                    if ($rawTtc === 103 && ($attempt !== 1 || $isQosh)) {
                        continue;
                    }

                    if ($isQosh) {
                        $slot = ($attempt === 3) ? 'a3' : 'q';
                    } else {
                        $slot = 'a' . $attempt;
                    }
                    $eff = ($slot === 'a1' && !$isQosh) ? $this->effMain($r) : $this->effAttempt($r);
                    if ($eff === null) {
                        continue;
                    }

                    if (!isset($map[$key])) {
                        $map[$key] = [
                            'student_id'  => (string) ($r->student_id_number ?? ''),
                            'full_name'   => (string) ($r->full_name ?? ''),
                            'group'       => (string) ($r->group_name ?? '-'),
                            'subject'     => (string) ($r->subject_name ?? ''),
                            'semester'    => '-',
                            'kurs'        => '-',
                            '_sem_rank'   => 999,
                            'oski'   => ['a1' => [], 'a2' => [], 'a3' => [], 'q' => []],
                            'test'   => ['a1' => [], 'a2' => [], 'a3' => [], 'q' => []],
                            'oski_d' => ['a1' => null, 'a2' => null, 'a3' => null, 'q' => null],
                            'test_d' => ['a1' => null, 'a2' => null, 'a3' => null, 'q' => null],
                        ];
                    }

                    // Semestr/kurs — eng kichik urinish (1-urinish) yozuvidagi
                    // qiymat eng vakili: o'sha asosiy o'quv semestri.
                    if ($attempt < $map[$key]['_sem_rank']) {
                        $map[$key]['_sem_rank'] = $attempt;
                        $map[$key]['semester'] = (string) ($r->semester_name ?: ($r->semester_code ?? '-'));
                        $kn = $this->kursFromSemester($r->semester_name, $r->semester_code);
                        $map[$key]['kurs'] = $kn ? $kn . '-kurs' : '-';
                    }

                    $map[$key][$type][$slot][] = $eff;

                    // Imtihon sanasi — slotda bir nechta yozuv bo'lsa eng so'nggisi.
                    if (!empty($r->lesson_date)) {
                        $ds = is_string($r->lesson_date)
                            ? substr($r->lesson_date, 0, 10)
                            : \Carbon\Carbon::parse($r->lesson_date)->format('Y-m-d');
                        $curD = $map[$key][$type . '_d'][$slot];
                        if ($curD === null || $ds > $curD) {
                            $map[$key][$type . '_d'][$slot] = $ds;
                        }
                    }
                }
                unset($rows);

                $ci++;
                $percent = 15 + (int) (55 * ($ci / max(1, $totalChunks)));
                $this->updateStatus('running', "Guruhlanmoqda... ({$ci}/{$totalChunks})", min(70, $percent));
            }

            $this->updateStatus('running', 'Saralanmoqda...', 75);

            $avg = function (array $vals): ?float {
                if (empty($vals)) {
                    return null;
                }
                return round(array_sum($vals) / count($vals), 1);
            };

            $data = array_values($map);
            usort($data, function ($a, $b) {
                $c = strnatcmp($a['group'], $b['group']);
                if ($c !== 0) return $c;
                $c = strcmp($a['full_name'], $b['full_name']);
                if ($c !== 0) return $c;
                return strcmp($a['subject'], $b['subject']);
            });

            $fmtDate = fn(?string $d): ?string => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : null;

            $excelRows = [];
            $i = 0;
            foreach ($data as $d) {
                $i++;
                $o  = $d['oski'];
                $od = $d['oski_d'];
                $t  = $d['test'];
                $td = $d['test_d'];
                $excelRows[] = [
                    $i,
                    $d['student_id'],
                    $d['full_name'],
                    $d['kurs'],
                    $d['group'],
                    $d['subject'],
                    $d['semester'],
                    $avg($o['a1']), $fmtDate($od['a1']),
                    $avg($o['a2']), $fmtDate($od['a2']),
                    $avg($o['a3']), $fmtDate($od['a3']),
                    $avg($o['q']),  $fmtDate($od['q']),
                    $avg($t['a1']), $fmtDate($td['a1']),
                    $avg($t['a2']), $fmtDate($td['a2']),
                    $avg($t['a3']), $fmtDate($td['a3']),
                    $avg($t['q']),  $fmtDate($td['q']),
                ];
            }

            $this->updateStatus('running', 'Excel fayl yaratilmoqda...', 85);

            $this->storeExcel($excelRows, $from, $to);

            Log::info('[JournalExamGradesExportJob] Tayyor', [
                'export_key' => $this->exportKey,
                'rows'       => count($excelRows),
            ]);

            $this->updateStatus('done', 'Tayyor', 100, $this->fileName($from, $to));
        } catch (\Throwable $e) {
            Log::error('[JournalExamGradesExportJob] Xato: ' . $e->getMessage(), [
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);
            $this->updateStatus('failed', 'Xato: ' . mb_substr($e->getMessage(), 0, 120), 0);
        }
    }

    private function fileName(string $from, string $to): string
    {
        return 'jurnal_oski_test_' . $from . '_' . $to . '.xlsx';
    }

    private function storeExcel(array $excelRows, string $from, string $to): void
    {
        Excel::store(
            new JournalExamGradesExport($excelRows, $from, $to),
            self::relativeXlsx($this->exportKey),
            'local'
        );
    }

    private function updateStatus(string $status, string $message, int $percent, ?string $fileName = null): void
    {
        $payload = [
            'status'     => $status,
            'message'    => $message,
            'percent'    => $percent,
            'file_name'  => $fileName,
            'updated_at' => now()->toDateTimeString(),
        ];

        try {
            Cache::put($this->exportKey, $payload, 1800);
        } catch (\Throwable $e) {
            Log::warning('[JournalExamGradesExportJob] Cache::put muvaffaqiyatsiz: ' . $e->getMessage());
        }

        try {
            $paths = self::pathsFor($this->exportKey);
            file_put_contents($paths['meta'], json_encode($payload));
        } catch (\Throwable $e) {
            Log::warning('[JournalExamGradesExportJob] Meta yozish muvaffaqiyatsiz: ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[JournalExamGradesExportJob] Failed: ' . $exception->getMessage());
        $this->updateStatus('failed', mb_substr($exception->getMessage(), 0, 120), 0);
    }
}
