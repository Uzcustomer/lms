<?php

namespace App\Console\Commands;

use App\Services\TableImageGenerator;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendFiveCandidateLowGrades extends Command
{
    protected $signature = 'five-candidates:send-low-grades
        {--date= : Tekshiriladigan sana (Y-m-d), default: bugun}
        {--chat-id= : Test uchun shaxsiy Telegram chat_id}
        {--score-limit=90 : Minimal baho chegarasi}';

    protected $description = '5 ga da\'vogar talabalar 90 dan past baho olsa Telegram guruhga jadval rasm yuborish';

    public function handle(TelegramService $telegram): int
    {
        $dateStr = $this->option('date') ?: Carbon::today()->format('Y-m-d');
        $chatId = $this->option('chat-id') ?: config('services.telegram.five_candidate_group_id');
        $scoreLimit = (int) $this->option('score-limit');

        if (!$chatId) {
            $this->error('Telegram chat_id sozlanmagan. TELEGRAM_FIVE_CANDIDATE_GROUP_ID ni .env ga qo\'shing.');
            return 1;
        }

        $this->info("Sana: {$dateStr}, Baho chegarasi: < {$scoreLimit}");

        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // Joriy semestr schedule combos
        $scheduleCombos = DB::table('schedules as sch')
            ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
            })
            ->where('sem.current', true)
            ->where('sch.education_year_current', true)
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->select('sch.group_id', 'sch.subject_id', 'sch.semester_code')
            ->distinct()
            ->get();

        if ($scheduleCombos->isEmpty()) {
            $this->info('Schedule combos topilmadi.');
            return 0;
        }

        $scheduleGroupIds = $scheduleCombos->pluck('group_id')->unique()->toArray();

        // 5 ga da'vogar talabalar
        $students = DB::table('students as s')
            ->select('s.hemis_id', 's.full_name', 's.group_id', 's.group_name', 's.student_id_number')
            ->where('s.is_five_candidate', true)
            ->whereIn('s.group_id', $scheduleGroupIds)
            ->get();

        if ($students->isEmpty()) {
            $this->info('5 ga da\'vogar talabalar topilmadi.');
            return 0;
        }

        $studentHemisIds = $students->pluck('hemis_id')->toArray();
        $studentMap = $students->keyBy('hemis_id');

        // Bugungi baholarni olish
        $grades = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereRaw('DATE(lesson_date) = ?', [$dateStr])
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->select('student_hemis_id', 'subject_id', 'subject_name', 'training_type_code',
                'training_type_name', 'grade', 'lesson_date', 'reason')
            ->get();

        if ($grades->isEmpty()) {
            $this->info("Bugungi sanada ({$dateStr}) baholar topilmadi.");
            return 0;
        }

        // Talaba + fan + dars turi bo'yicha guruhlash va o'rtachani hisoblash
        $lowGrades = [];

        $grouped = [];
        foreach ($grades as $g) {
            $key = $g->student_hemis_id . '|' . $g->subject_id . '|' . $g->training_type_code;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'student_hemis_id' => $g->student_hemis_id,
                    'subject_name' => $g->subject_name,
                    'training_type_name' => $g->training_type_name,
                    'grades' => [],
                    'absent' => false,
                ];
            }

            if ($g->reason === 'absent') {
                $grouped[$key]['absent'] = true;
            }
            $grouped[$key]['grades'][] = (float) $g->grade;
        }

        foreach ($grouped as $data) {
            $avg = count($data['grades']) > 0
                ? round(array_sum($data['grades']) / count($data['grades']))
                : 0;

            if ($data['absent']) {
                $avg = 0;
            }

            if ($avg < $scoreLimit) {
                $st = $studentMap[$data['student_hemis_id']] ?? null;
                if (!$st) continue;

                $lowGrades[] = [
                    'full_name' => $st->full_name,
                    'student_id' => $st->student_id_number,
                    'group_name' => $st->group_name,
                    'subject_name' => $data['subject_name'],
                    'training_type' => $data['training_type_name'],
                    'grade' => $avg,
                    'absent' => $data['absent'],
                ];
            }
        }

        if (empty($lowGrades)) {
            $this->info("Bugun {$scoreLimit} dan past baho olgan 5 ga da'vogar talabalar yo'q.");
            return 0;
        }

        // Guruh bo'yicha saralash
        usort($lowGrades, function ($a, $b) {
            $cmp = strcasecmp($a['group_name'], $b['group_name']);
            if ($cmp !== 0) return $cmp;
            return strcasecmp($a['full_name'], $b['full_name']);
        });

        // Jadval qatorlarini tayyorlash (guruh sarlavha qatorlari bilan)
        $tableRows = [];
        $currentGroup = null;
        $num = 0;

        foreach ($lowGrades as $row) {
            if ($row['group_name'] !== $currentGroup) {
                $currentGroup = $row['group_name'];
            }

            $num++;
            $gradeText = $row['absent'] ? 'NB' : (string) $row['grade'];

            $tableRows[] = [
                $num,
                TableImageGenerator::truncate($row['full_name'], 24),
                $row['group_name'] ?? '-',
                TableImageGenerator::truncate($row['subject_name'], 24),
                TableImageGenerator::truncate($row['training_type'], 14),
                $gradeText,
            ];
        }

        $formattedDate = Carbon::parse($dateStr)->format('d.m.Y');
        $headers = ['#', 'TALABA FISH', 'GURUH', 'FAN', "MASHG'ULOT TURI", 'BAHO'];
        $title = "5 GA DA'VOGARLAR — {$scoreLimit} DAN PAST BAHOLAR — {$formattedDate} (Jami: {$num})";

        $generator = (new TableImageGenerator())->compact();
        $images = $generator->generate($headers, $tableRows, $title);

        $tempFiles = [];

        try {
            foreach ($images as $index => $imagePath) {
                $tempFiles[] = $imagePath;
                $caption = "5 ga da'vogarlar — {$scoreLimit} dan past baholar — {$formattedDate}";
                if (count($images) > 1) {
                    $caption .= ' (' . ($index + 1) . '/' . count($images) . '-sahifa)';
                }
                $telegram->sendPhoto($chatId, $imagePath, $caption);
            }

            $this->info("Telegram guruhga {$num} ta past baho haqida jadval rasm yuborildi.");
            Log::info("5 ga da'vogarlar past baholar: {$num} ta, sana: {$dateStr}");
        } catch (\Throwable $e) {
            Log::error("5 ga da'vogarlar Telegram yuborishda xato: " . $e->getMessage());
            $this->error('Xato: ' . $e->getMessage());
            return 1;
        } finally {
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }

        return 0;
    }
}
