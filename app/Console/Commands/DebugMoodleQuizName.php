<?php

namespace App\Console\Commands;

use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\HemisQuizResult;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Console\Command;

class DebugMoodleQuizName extends Command
{
    protected $signature = 'debug:moodle-quiz-name {schedule_id} {--yn=test : test yoki oski} {--attempt=1 : 1|2|3}';
    protected $description = 'YN jadval yozuvi uchun Moodle quiz nomi qanday qurilishini ko\'rsatadi (step 1 va step 2 manbalari).';

    public function handle(): int
    {
        $scheduleId = (int) $this->argument('schedule_id');
        $ynType = strtolower((string) $this->option('yn'));
        $attempt = max(1, min(3, (int) $this->option('attempt')));

        if (!in_array($ynType, ['test', 'oski'], true)) {
            $this->error('--yn = test yoki oski bo\'lishi kerak');
            return 1;
        }

        $schedule = ExamSchedule::find($scheduleId);
        if (!$schedule) {
            $this->error("ExamSchedule#{$scheduleId} topilmadi");
            return 1;
        }

        $this->info("=== Schedule#{$schedule->id} ===");
        $this->line('subject_id: ' . $schedule->subject_id);
        $this->line('subject_name: ' . $schedule->subject_name);
        $this->line('group_hemis_id: ' . $schedule->group_hemis_id);
        $this->line('semester_code: ' . $schedule->semester_code);
        $this->line('education_year: ' . $schedule->education_year);
        $this->line('');

        $group = Group::where('group_hemis_id', $schedule->group_hemis_id)->first();
        $groupLang = $this->normalizeLang($group?->education_lang_code);
        $this->line('group.education_lang_code: ' . ($group->education_lang_code ?? 'NULL') . " -> {$groupLang}");

        $semesterName = Semester::where('code', $schedule->semester_code)->value('name');
        $targetNsem = null;
        if ($semesterName && preg_match('/(\d+)/', (string) $semesterName, $m)) {
            $targetNsem = $m[1] . '-sem';
        }
        $this->line('semester.name: ' . ($semesterName ?? 'NULL') . " -> targetNsem={$targetNsem}");

        $groupStudentIds = Student::where('group_id', $schedule->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->pluck('student_id_number')
            ->all();
        $this->line('group students: ' . count($groupStudentIds));
        $this->line('');

        $prefix = $ynType === 'oski' ? 'OSKI' : 'YN test';

        // --- Step 1: hemis_quiz_results for this group+subject -----------------
        $this->info("=== Step 1: recorded {$prefix} attempts for this group+subject ===");
        $step1 = HemisQuizResult::query()
            ->where('fan_id', $schedule->subject_id)
            ->whereIn('student_id', $groupStudentIds)
            ->whereNotNull('attempt_name')
            ->where('attempt_name', 'LIKE', $prefix . ' (%')
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'student_id', 'attempt_name', 'synced_at']);

        if ($step1->isEmpty()) {
            $this->warn('  (nothing — step 2 fallback bo\'ladi)');
        } else {
            foreach ($step1 as $row) {
                $middle = $this->extractQuizMiddle((string) $row->attempt_name, $prefix);
                $this->line(sprintf(
                    '  #%d  st=%s  name="%s"  ->  middle="%s"',
                    $row->id,
                    $row->student_id,
                    $row->attempt_name,
                    $middle ?? '(no match)'
                ));
            }
        }
        $this->line('');

        // --- Step 2: tails from any of this group's recorded attempts ----------
        $this->info("=== Step 2: tail candidates from this group's recorded quizzes ===");
        $any = HemisQuizResult::query()
            ->whereIn('student_id', $groupStudentIds)
            ->whereNotNull('attempt_name')
            ->where(function ($q) {
                $q->where('attempt_name', 'LIKE', 'YN test (%')
                  ->orWhere('attempt_name', 'LIKE', 'OSKI (%')
                  ->orWhere('attempt_name', 'LIKE', 'JN (%');
            })
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'fan_id', 'student_id', 'attempt_name', 'synced_at']);

        $strippedSubject = $this->stripGroupSuffix(trim((string) $schedule->subject_name));
        $this->line('subject_name (stripped): ' . $strippedSubject);
        $this->line('');

        if ($any->isEmpty()) {
            $this->warn('  (nothing — quiz name qurib bo\'lmaydi)');
        } else {
            $pickedTail = null;
            foreach ($any as $row) {
                $tail = $this->extractMiddleTail((string) $row->attempt_name);
                $matches = $tail !== null && $targetNsem !== null && str_starts_with($tail, $targetNsem . '_');
                $marker = $matches && $pickedTail === null ? ' <-- WOULD USE' : '';
                if ($matches && $pickedTail === null) {
                    $pickedTail = $tail;
                }
                $this->line(sprintf(
                    '  fan=%s  st=%s  tail=%s%s  | name="%s"',
                    $row->fan_id,
                    $row->student_id,
                    $tail ?? '(no match)',
                    $marker,
                    $row->attempt_name
                ));
            }
            $this->line('');
            if ($pickedTail) {
                $built = $strippedSubject . '_' . $pickedTail;
                $this->info('Step 2 would build middle: ' . $built);
            } else {
                $this->warn('Step 2: no tail matched targetNsem=' . $targetNsem);
            }
        }
        $this->line('');

        // --- Final constructed quiz name(s) per language -----------------------
        $this->info('=== Final quiz_name candidates that would be sent to Moodle ===');
        $studentsByLang = $this->studentsByLanguage($schedule->group_hemis_id);
        foreach ($studentsByLang as $lang => $usernames) {
            $middle = null;
            $source = null;
            foreach ($step1 as $row) {
                $m = $this->extractQuizMiddle((string) $row->attempt_name, $prefix);
                if ($m !== null) { $middle = $m; $source = 'step1'; break; }
            }
            if ($middle === null && isset($pickedTail) && $pickedTail) {
                $middle = $strippedSubject . '_' . $pickedTail;
                $source = 'step2';
            }
            if ($middle === null) {
                $this->error("  [{$lang}] (no middle resolvable) — booking would be SKIPPED");
                continue;
            }
            $quizName = $prefix . ' (' . $lang . ')_' . $middle . '_' . $attempt . '-urinish';
            $this->line(sprintf('  [%s] (%s) %d student(s)  ->  "%s"', $lang, $source, count($usernames), $quizName));
        }

        return 0;
    }

    private function extractQuizMiddle(string $quizName, string $prefix): ?string
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '\\s*\\([^)]*\\)_(.+)_[^_]+$/u';
        if (preg_match($pattern, trim($quizName), $m)) {
            $middle = trim($m[1]);
            return $middle !== '' ? $middle : null;
        }
        return null;
    }

    private function extractMiddleTail(string $quizName): ?string
    {
        if (preg_match('/_(\d+-sem_.+)_[^_]+$/u', trim($quizName), $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function stripGroupSuffix(string $name): string
    {
        return trim(preg_replace('/\s*\([A-Za-z0-9]{1,4}\)\s*$/u', '', $name));
    }

    private function studentsByLanguage(string $groupHemisId): array
    {
        $group = Group::where('group_hemis_id', $groupHemisId)->first();
        $defaultLang = $this->normalizeLang($group?->education_lang_code);

        $students = Student::where('group_id', $groupHemisId)
            ->whereNotNull('student_id_number')
            ->get(['student_id_number', 'exam_language_code']);

        $bucket = [];
        foreach ($students as $st) {
            $lang = $this->normalizeLang($st->exam_language_code ?: $defaultLang);
            $bucket[$lang][] = (string) $st->student_id_number;
        }
        foreach ($bucket as $k => $list) {
            $bucket[$k] = array_values(array_unique($list));
        }
        return $bucket;
    }

    private function normalizeLang(?string $code): string
    {
        $code = strtolower(trim((string) $code));
        if ($code === '') {
            return (string) (config('services.moodle.lang_map.uz') ?? 'uzb');
        }
        $map = (array) config('services.moodle.lang_map', []);
        if (isset($map[$code])) {
            return (string) $map[$code];
        }
        if (in_array($code, $map, true)) {
            return $code;
        }
        return match ($code) {
            'uz', 'oz', 'uzb' => 'uzb',
            'ru', 'rus' => 'rus',
            'en', 'eng' => 'eng',
            default => $code,
        };
    }
}
