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
 * Baholar jurnal sahifasidagi bilan AYNAN bir xil mantiq bilan olinadi:
 *  - 1-urinish (asosiy ustun): jurnal getEffectiveGrade qoidasi
 *    (asl baho 60 dan past + retake bo'lsa retake ustun, status/reason
 *    hisobga olinadi);
 *  - 2/3-urinish va qo'shimcha farmoyish: jurnal fetchAttemptOskiTest
 *    qoidasi;
 *  - legacy 103 kodli quiz baholari quiz_type orqali OSKI(101)/Test(102)
 *    ga aylantiriladi;
 *  - bir urinishda bir nechta yozuv bo'lsa — o'rtacha (jurnaldagidek).
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
            $this->updateStatus('running', 'Baholar yuklanmoqda...', 5);

            $from     = $this->filters['from'];
            $to       = $this->filters['to'];
            $kurslar  = $this->filters['kurslar'] ?? [];
            $facultyHemisIds = $this->filters['faculty_hemis_ids'] ?? [];

            $hasAttemptCol   = Schema::hasColumn('student_grades', 'attempt');
            $hasQoshimchaCol = Schema::hasColumn('student_grades', 'is_qoshimcha');

            // OSKI (101), Test (102) va legacy quiz (103) baholari.
            $q = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
                ->whereNull('sg.deleted_at')
                ->whereIn('sg.training_type_code', [101, 102, 103])
                ->whereBetween('sg.lesson_date', [$from . ' 00:00:00', $to . ' 23:59:59']);
            if (!empty($facultyHemisIds)) {
                $q->whereIn('g.department_hemis_id', $facultyHemisIds);
            }
            $select = [
                'sg.student_hemis_id', 'sg.subject_id', 'sg.subject_name',
                'sg.semester_code', 'sg.semester_name', 'sg.training_type_code',
                'sg.grade', 'sg.retake_grade', 'sg.status', 'sg.reason', 'sg.quiz_result_id',
                'st.full_name', 'st.student_id_number', 'st.group_name',
            ];
            if ($hasAttemptCol)   $select[] = 'sg.attempt';
            if ($hasQoshimchaCol) $select[] = 'sg.is_qoshimcha';
            $grades = $q->select($select)->get();

            $total = $grades->count();
            $this->updateStatus('running', "Baholar guruhlanmoqda... ({$total} ta yozuv)", 25);

            // Legacy 103 kodlarni quiz_type orqali OSKI/Test ga aylantirish uchun
            // quiz_type'larni batch yuklaymiz.
            $quizTypeMap = [];
            $quizResultIds = $grades
                ->where('training_type_code', 103)
                ->pluck('quiz_result_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            if (!empty($quizResultIds)) {
                $quizTypeMap = DB::table('hemis_quiz_results')
                    ->whereIn('id', $quizResultIds)
                    ->pluck('quiz_type', 'id')
                    ->all();
            }

            // (talaba, fan, semestr) bo'yicha guruhlash — har urinish/qo'shimcha
            // uchun baholar ro'yxati yig'iladi, keyin o'rtachasi olinadi.
            $map = [];
            $processed = 0;
            foreach ($grades as $r) {
                $processed++;
                if ($processed % 2000 === 0) {
                    $percent = 25 + (int) (45 * ($processed / max(1, $total)));
                    $this->updateStatus('running', "Guruhlanmoqda... ({$processed}/{$total})", min(70, $percent));
                }

                $rawTtc = (int) $r->training_type_code;

                // Legacy 103 -> 101/102.
                $ttc = $rawTtc;
                if ($rawTtc === 103) {
                    $qt = $r->quiz_result_id ? ($quizTypeMap[$r->quiz_result_id] ?? null) : null;
                    if (in_array($qt, self::OSKI_QUIZ_TYPES, true)) {
                        $ttc = 101;
                    } elseif (in_array($qt, self::TEST_QUIZ_TYPES, true)) {
                        $ttc = 102;
                    } else {
                        continue; // aniqlanmagan legacy quiz — jurnalda ham e'tiborsiz
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

                // Ustun (slot) va effektiv baho qoidasini aniqlash.
                if ($isQosh) {
                    $slot = ($attempt === 3) ? 'a3' : 'q';
                } else {
                    $slot = 'a' . $attempt;
                }
                $eff = ($slot === 'a1' && !$isQosh) ? $this->effMain($r) : $this->effAttempt($r);
                if ($eff === null) {
                    continue;
                }

                // Kurs (semestrdan) — kurslar filtri.
                $semNum = null;
                if (!empty($r->semester_name) && preg_match('/(\d+)/', (string) $r->semester_name, $m)) {
                    $semNum = (int) $m[1];
                } elseif (!empty($r->semester_code) && preg_match('/(\d+)/', (string) $r->semester_code, $m2)) {
                    $semNum = (int) $m2[1];
                }
                $kurs = $semNum ? (int) ceil($semNum / 2) : null;
                if (!empty($kurslar) && (!$kurs || !in_array($kurs, $kurslar, true))) {
                    continue;
                }

                $key = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                if (!isset($map[$key])) {
                    $map[$key] = [
                        'student_id' => (string) ($r->student_id_number ?? ''),
                        'full_name'  => (string) ($r->full_name ?? ''),
                        'kurs'       => $kurs ? $kurs . '-kurs' : '-',
                        'group'      => (string) ($r->group_name ?? '-'),
                        'subject'    => (string) ($r->subject_name ?? ''),
                        'semester'   => (string) ($r->semester_name ?: ($r->semester_code ?? '')),
                        'oski' => ['a1' => [], 'a2' => [], 'a3' => [], 'q' => []],
                        'test' => ['a1' => [], 'a2' => [], 'a3' => [], 'q' => []],
                    ];
                }

                $map[$key][$type][$slot][] = $eff;
            }
            unset($grades);

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

            $excelRows = [];
            $i = 0;
            foreach ($data as $d) {
                $i++;
                $o = $d['oski'];
                $t = $d['test'];
                $excelRows[] = [
                    $i,
                    $d['student_id'],
                    $d['full_name'],
                    $d['kurs'],
                    $d['group'],
                    $d['subject'],
                    $d['semester'],
                    $avg($o['a1']), $avg($o['a2']), $avg($o['a3']), $avg($o['q']),
                    $avg($t['a1']), $avg($t['a2']), $avg($t['a3']), $avg($t['q']),
                ];
            }

            $this->updateStatus('running', 'Excel fayl yaratilmoqda...', 85);

            Excel::store(
                new JournalExamGradesExport($excelRows, $from, $to),
                self::relativeXlsx($this->exportKey),
                'local'
            );

            $fileName = 'jurnal_oski_test_' . $from . '_' . $to . '.xlsx';

            Log::info('[JournalExamGradesExportJob] Tayyor', [
                'export_key' => $this->exportKey,
                'rows'       => count($excelRows),
            ]);

            $this->updateStatus('done', 'Tayyor', 100, $fileName);
        } catch (\Throwable $e) {
            Log::error('[JournalExamGradesExportJob] Xato: ' . $e->getMessage(), [
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);
            $this->updateStatus('failed', 'Xato: ' . mb_substr($e->getMessage(), 0, 120), 0);
        }
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
