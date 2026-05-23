<?php

namespace App\Console\Commands;

use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\HemisQuizResult;
use App\Models\Semester;
use App\Models\Student;
use App\Services\MoodleExamBookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseMoodleBook extends Command
{
    protected $signature = 'diagnose:moodle-book
        {--schedule_id= : ExamSchedule.id}
        {--group= : group_hemis_id (alternatively to schedule_id)}
        {--subject= : subject_id (used with --group)}
        {--semester= : semester_code (used with --group)}
        {--yn=test : test yoki oski}
        {--attempt=3 : 1|2|3}
        {--push : Moodle ga jo\'natib, javobini ko\'rsatish (default: faqat resolution+oxirgi persisted javob)}';

    protected $description = 'Bir ExamSchedule yozuvi uchun Moodle booking nima qilayotganini to\'liq dump qiladi (resolved quiz_name, request payload, response, persisted error).';

    public function handle(): int
    {
        $ynType = strtolower((string) $this->option('yn'));
        $attempt = max(1, min(3, (int) $this->option('attempt')));
        if (!in_array($ynType, ['test', 'oski'], true)) {
            $this->error('--yn = test yoki oski');
            return 1;
        }

        $schedule = $this->locateSchedule();
        if (!$schedule) {
            $this->error('ExamSchedule topilmadi.');
            return 1;
        }

        $this->info("=== ExamSchedule#{$schedule->id} ===");
        $this->table(['field', 'value'], [
            ['subject_id', $schedule->subject_id],
            ['subject_name', $schedule->subject_name],
            ['group_hemis_id', $schedule->group_hemis_id],
            ['semester_code', $schedule->semester_code],
            ['education_year', $schedule->education_year],
            ['student_hemis_id', $schedule->student_hemis_id ?? '(null — group-level)'],
        ]);

        $prefix = $this->attemptPrefix($ynType, $attempt);
        $dateField = $prefix . '_date';
        $timeField = $prefix . '_time';
        $errorField = $prefix . '_moodle_error';
        $syncField = $prefix . '_moodle_synced_at';
        $respField = $prefix . '_moodle_response';

        $this->info("\n=== Attempt {$attempt} state (prefix={$prefix}) ===");
        $this->table(['field', 'value'], [
            [$dateField, $schedule->{$dateField} ?? '(null)'],
            [$timeField, $schedule->{$timeField} ?? '(null)'],
            [$syncField, $schedule->{$syncField} ?? '(never synced)'],
            [$errorField, substr((string) $schedule->{$errorField}, 0, 500) ?: '(no error)'],
        ]);

        // ---- Resolve quiz_middle exactly like MoodleExamBookingService::resolveQuizMiddle.
        $groupStudentIds = Student::where('group_id', $schedule->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->pluck('student_id_number')
            ->all();
        $this->info("\n=== Group context ===");
        $this->line('  group students with student_id_number: ' . count($groupStudentIds));

        $semesterName = Semester::where('code', $schedule->semester_code)->value('name');
        $targetNsem = null;
        if ($semesterName && preg_match('/(\d+)/', (string) $semesterName, $m)) {
            $targetNsem = $m[1] . '-sem';
        }
        $this->line('  semester.name=' . ($semesterName ?? 'NULL') . " -> targetNsem={$targetNsem}");

        $namePrefix = $ynType === 'oski' ? 'OSKI' : 'YN test';

        // Step 1: same subject for this group.
        $this->info("\n=== Step 1: this group's recorded {$namePrefix} attempts for subject_id={$schedule->subject_id} ===");
        $step1 = HemisQuizResult::query()
            ->where('fan_id', $schedule->subject_id)
            ->whereIn('student_id', $groupStudentIds)
            ->whereNotNull('attempt_name')
            ->where('attempt_name', 'LIKE', $namePrefix . ' (%')
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'student_id', 'attempt_name', 'synced_at']);

        $step1Middle = null;
        if ($step1->isEmpty()) {
            $this->warn('  (no rows — step 2 fallback)');
        } else {
            foreach ($step1 as $row) {
                $mid = $this->extractQuizMiddle((string) $row->attempt_name, $namePrefix);
                if ($step1Middle === null && $mid !== null) $step1Middle = $mid;
                $this->line(sprintf('  #%d  st=%s  "%s"  -> middle="%s"',
                    $row->id, $row->student_id, $row->attempt_name, $mid ?? '(no match)'));
            }
        }

        // Step 2: tail from any of this group's recorded quizzes.
        $this->info("\n=== Step 2: tail candidates from this group's recorded quizzes (any subject) ===");
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
            ->get(['id', 'fan_id', 'student_id', 'attempt_name']);

        $strippedSubject = $this->stripGroupSuffix(trim((string) $schedule->subject_name));
        $this->line('  subject_name (stripped) = "' . $strippedSubject . '"');

        $step2Middle = null;
        if ($any->isEmpty()) {
            $this->warn('  (no recorded quizzes for this group at all)');
        } else {
            foreach ($any as $row) {
                $tail = $this->extractMiddleTail((string) $row->attempt_name);
                $matches = $tail !== null && $targetNsem !== null && str_starts_with($tail, $targetNsem . '_');
                $marker = $matches && $step2Middle === null ? ' <-- PICKED' : '';
                if ($matches && $step2Middle === null) {
                    $step2Middle = $strippedSubject . '_' . $tail;
                }
                $this->line(sprintf('  fan=%s st=%s tail=%s%s | "%s"',
                    $row->fan_id, $row->student_id, $tail ?? '(no match)', $marker, $row->attempt_name));
            }
        }

        $quizMiddle = $step1Middle ?: $step2Middle;
        $this->info("\n=== Resolved quiz_middle ===");
        if ($quizMiddle === null) {
            $this->error('  (null) — booking BOSHLANMAYDI: "no usable Moodle quiz history for this group"');
        } else {
            $this->line('  source = ' . ($step1Middle ? 'step1 (exact subject)' : 'step2 (tail reuse)'));
            $this->line('  middle = "' . $quizMiddle . '"');
        }

        // Build per-language quiz names.
        $langTokens = $this->resolveLangQuizTokens();
        $this->info("\n=== Quiz names that will be sent to Moodle (per language) ===");
        $quizNames = [];
        foreach ($langTokens as $lang) {
            if ($quizMiddle === null) { $quizNames[$lang] = null; continue; }
            $name = $namePrefix . ' (' . $lang . ')_' . $quizMiddle . '_' . $attempt . '-urinish';
            $quizNames[$lang] = $name;
            $this->line('  [' . $lang . '] ' . $name);
        }

        // Persisted last response.
        $this->info("\n=== Last persisted response in {$respField} ===");
        $resp = $schedule->{$respField};
        if (is_array($resp) || is_object($resp)) {
            $this->line(json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } elseif (is_string($resp) && $resp !== '') {
            $this->line($resp);
        } else {
            $this->line('(empty)');
        }

        // Optional: actually push to Moodle now.
        if ($this->option('push')) {
            $this->info("\n=== Pushing to Moodle now (--push) ===");
            $unscheduled = empty($schedule->{$timeField});
            try {
                $result = app(MoodleExamBookingService::class)
                    ->book($schedule->fresh(), $ynType, $unscheduled, $attempt);
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                $this->error('exception: ' . $e->getMessage());
            }
            $fresh = $schedule->fresh();
            $this->info("\n=== After push: persisted error ===");
            $this->line(substr((string) $fresh->{$errorField}, 0, 1000) ?: '(no error)');
        } else {
            $this->info("\n(--push bermadingiz: Moodle ga jo'natilmadi. Yuborish uchun --push qo'shing.)");
        }

        $this->info("\n=== Moodle tomonda tekshirish uchun ===");
        $this->line('  Moodle serverida quyidagini ishlating:');
        foreach ($quizNames as $lang => $name) {
            if ($name !== null) {
                $this->line('    sudo -u www-data php local/hemisexport/cli/diagnose_quiz_lookup.php --name=' . escapeshellarg($name)
                    . ' --fanid=' . $schedule->subject_id
                    . ' --year=' . $schedule->education_year);
            }
        }

        return 0;
    }

    private function locateSchedule(): ?ExamSchedule
    {
        if ($id = $this->option('schedule_id')) {
            return ExamSchedule::find((int) $id);
        }
        $group = $this->option('group');
        if (!$group) {
            return null;
        }
        $q = ExamSchedule::where('group_hemis_id', $group);
        if ($s = $this->option('subject')) $q->where('subject_id', $s);
        if ($sm = $this->option('semester')) $q->where('semester_code', $sm);
        return $q->orderByDesc('id')->first();
    }

    private function attemptPrefix(string $ynType, int $attempt): string
    {
        return match ($attempt) {
            2 => $ynType . '_resit',
            3 => $ynType . '_resit2',
            default => $ynType,
        };
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
        return trim(preg_replace('/\s*\([A-Za-zА-Яа-яёЁ0-9]{1,4}\)\s*$/u', '', $name));
    }

    private function resolveLangQuizTokens(): array
    {
        $map = (array) config('services.moodle.lang_map', []);
        $tokens = array_values(array_unique(array_map(
            fn ($v) => strtolower((string) $v),
            array_filter($map, fn ($v) => is_string($v) && $v !== '')
        )));
        return !empty($tokens) ? $tokens : ['uzb', 'rus', 'eng'];
    }
}
