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
 * Sana oralig'i, fakultetlar va kurslar bo'yicha barcha fanlardan OSKI va
 * Test baholari. Holat (foiz) cache hamda diskdagi meta faylga yoziladi.
 */
class JournalExamGradesExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

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

            $q = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
                ->whereNull('sg.deleted_at')
                ->whereIn('sg.training_type_code', [101, 102])
                ->whereBetween('sg.lesson_date', [$from . ' 00:00:00', $to . ' 23:59:59']);
            if (!empty($facultyHemisIds)) {
                $q->whereIn('g.department_hemis_id', $facultyHemisIds);
            }
            $select = [
                'sg.student_hemis_id', 'sg.subject_id', 'sg.subject_name',
                'sg.semester_code', 'sg.semester_name', 'sg.training_type_code',
                'sg.grade', 'sg.retake_grade',
                'st.full_name', 'st.student_id_number', 'st.group_name',
            ];
            if ($hasAttemptCol)   $select[] = 'sg.attempt';
            if ($hasQoshimchaCol) $select[] = 'sg.is_qoshimcha';
            $grades = $q->select($select)->get();

            $total = $grades->count();
            $this->updateStatus('running', "Baholar guruhlanmoqda... ({$total} ta yozuv)", 30);

            // (talaba, fan, semestr) bo'yicha guruhlash.
            $map = [];
            $processed = 0;
            foreach ($grades as $r) {
                $processed++;
                if ($processed % 2000 === 0) {
                    $percent = 30 + (int) (40 * ($processed / max(1, $total)));
                    $this->updateStatus('running', "Guruhlanmoqda... ({$processed}/{$total})", min(70, $percent));
                }

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
                        'oski'   => [1 => null, 2 => null, 3 => null],
                        'oski_q' => null,
                        'test'   => [1 => null, 2 => null, 3 => null],
                        'test_q' => null,
                    ];
                }

                $grade = $r->retake_grade ?? $r->grade;
                if ($grade === null) {
                    continue;
                }
                $grade = (float) $grade;
                $attempt = $hasAttemptCol ? (int) ($r->attempt ?? 1) : 1;
                if ($attempt < 1 || $attempt > 3) {
                    $attempt = 1;
                }
                $type = ((int) $r->training_type_code === 101) ? 'oski' : 'test';

                if ($hasQoshimchaCol && !empty($r->is_qoshimcha)) {
                    $cur = $map[$key][$type . '_q'];
                    $map[$key][$type . '_q'] = ($cur === null) ? $grade : max($cur, $grade);
                } else {
                    $cur = $map[$key][$type][$attempt];
                    $map[$key][$type][$attempt] = ($cur === null) ? $grade : max($cur, $grade);
                }
            }
            unset($grades);

            $this->updateStatus('running', 'Saralanmoqda...', 75);

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
                $excelRows[] = [
                    $i,
                    $d['student_id'],
                    $d['full_name'],
                    $d['kurs'],
                    $d['group'],
                    $d['subject'],
                    $d['semester'],
                    $d['oski'][1], $d['oski'][2], $d['oski'][3], $d['oski_q'],
                    $d['test'][1], $d['test'][2], $d['test'][3], $d['test_q'],
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
