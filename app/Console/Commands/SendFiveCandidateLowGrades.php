<?php

namespace App\Console\Commands;

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

    protected $description = '5 ga da\'vogar talabalar 90 dan past baho olsa Telegram guruhga xabar yuborish';

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

        // Guruh bo'yicha guruhlash
        $byGroup = [];
        foreach ($lowGrades as $row) {
            $byGroup[$row['group_name']][] = $row;
        }
        ksort($byGroup);

        $date = Carbon::parse($dateStr)->format('d.m.Y');
        $lines = [];
        $lines[] = "<b>⚠️ 5 ga da'vogarlar — {$scoreLimit} dan past baholar</b>";
        $lines[] = "<b>📅 Sana: {$date}</b>";
        $lines[] = "";

        $num = 0;
        foreach ($byGroup as $groupName => $rows) {
            $lines[] = "<b>📚 {$groupName}</b>";

            foreach ($rows as $row) {
                $num++;
                $gradeText = $row['absent'] ? 'NB (sababsiz)' : $row['grade'];
                $lines[] = "{$num}. <b>{$row['full_name']}</b>";
                $lines[] = "   📖 {$row['subject_name']} ({$row['training_type']})";
                $lines[] = "   📊 Baho: <b>{$gradeText}</b>";
            }
            $lines[] = "";
        }

        $lines[] = "<b>Jami: {$num} ta past baho</b>";

        $message = implode("\n", $lines);

        // Telegram xabar 4096 belgidan oshmasligi kerak
        $chunks = mb_str_split($message, 4000);

        foreach ($chunks as $i => $chunk) {
            $telegram->sendToUser($chatId, $chunk);
            if ($i < count($chunks) - 1) {
                usleep(500000); // 0.5s kutish
            }
        }

        $this->info("Telegram guruhga {$num} ta past baho haqida xabar yuborildi.");
        Log::info("5 ga da'vogarlar past baholar: {$num} ta, sana: {$dateStr}");

        return 0;
    }
}
