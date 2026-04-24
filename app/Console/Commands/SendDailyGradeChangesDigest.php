<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyGradeChangesDigest extends Command
{
    protected $signature = 'grades:send-daily-changes-digest
                            {--hours=24 : Oxirgi necha soatlik ozgarishlarni olish}
                            {--late-days=2 : Necha kun kech kiritilgan baho "kechikib qoyilgan" deb hisoblanadi}';

    protected $description = "Admin chatiga kechagi sutkadagi otrabotka va o'zgartirilgan baholar jadvalini yuboradi";

    private const RETAKE_FIELDS = [
        'retake_grade',
        'retake_graded_at',
        'retake_file_path',
        'retake_by',
    ];

    private const TELEGRAM_MAX_CHARS = 3800;

    public function handle(TelegramService $telegram): int
    {
        $chatId = config('services.telegram.chat_id');
        if (empty($chatId)) {
            $this->warn('TELEGRAM_CHAT_ID sozlanmagan, digest yuborilmadi.');
            return self::SUCCESS;
        }

        $hours = (int) $this->option('hours') ?: 24;
        $lateDays = max(1, (int) $this->option('late-days'));
        $since = Carbon::now('Asia/Tashkent')->subHours($hours);
        $until = Carbon::now('Asia/Tashkent');

        $logs = ActivityLog::query()
            ->where('module', 'student_grade')
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->get();

        $retakeRows = [];
        $modifiedRows = [];
        $lateCreateRows = [];
        $deletedRows = [];

        $gradeIds = $logs->pluck('subject_id')->filter()->unique()->values();
        $grades = StudentGrade::withTrashed()
            ->whereIn('id', $gradeIds)
            ->get()
            ->keyBy('id');

        $studentIds = $grades->pluck('student_id')->filter()->unique()->values();
        $students = Student::whereIn('id', $studentIds)->get()->keyBy('id');

        foreach ($logs as $log) {
            $grade = $grades->get($log->subject_id);
            $new = $log->new_values ?? [];
            $old = $log->old_values ?? [];

            $context = $this->buildContext($log, $grade, $students);

            if ($log->action === 'delete') {
                $deletedRows[] = $context + [
                    'grade' => $this->fmt($old['grade'] ?? ($grade->grade ?? null)),
                    'retake_grade' => $this->fmt($old['retake_grade'] ?? ($grade->retake_grade ?? null)),
                ];
                continue;
            }

            $touchesRetake = (bool) array_intersect(self::RETAKE_FIELDS, array_keys($new));
            $isRetakeCreate = $log->action === 'create' && !empty($new['retake_grade']);

            if ($touchesRetake || $isRetakeCreate) {
                $retakeRows[] = $context + [
                    'from' => $log->action === 'update' ? $this->fmt($old['retake_grade'] ?? null) : '—',
                    'to'   => $this->fmt($new['retake_grade'] ?? ($grade->retake_grade ?? null)),
                    'was_sababli' => $grade->retake_was_sababli ?? null,
                ];
                continue;
            }

            if ($log->action === 'update' && array_key_exists('grade', $new)) {
                $modifiedRows[] = $context + [
                    'from' => $this->fmt($old['grade'] ?? null),
                    'to'   => $this->fmt($new['grade'] ?? null),
                ];
                continue;
            }

            // Kechikib qo'yilgan baho: o'qituvchi (yoki admin) dars o'tib ketgandan
            // keyin bo'sh joyga baho yaratgan. System importlarni hisobga olmaymiz.
            // "Kechikib" — lesson_date dan kamida `lateDays` kun o'tgan bo'lsa.
            if ($log->action === 'create'
                && !empty($log->user_id)
                && $grade
                && $grade->grade !== null
                && $grade->lesson_date
            ) {
                $lessonDay = Carbon::parse($grade->lesson_date)->startOfDay();
                $createdDay = Carbon::parse($log->created_at)->startOfDay();
                $delay = (int) $lessonDay->diffInDays($createdDay, false);
                if ($delay >= $lateDays) {
                    $lateCreateRows[] = $context + [
                        'to' => $this->fmt($grade->grade),
                        'delay_days' => $delay,
                    ];
                }
            }
        }

        if (empty($retakeRows) && empty($modifiedRows) && empty($lateCreateRows) && empty($deletedRows)) {
            $this->info('Oxirgi ' . $hours . ' soatda digestga arziydigan o\'zgarish yo\'q.');
            return self::SUCCESS;
        }

        $message = $this->formatMessage($retakeRows, $modifiedRows, $lateCreateRows, $deletedRows, $since, $until);
        $chunks = $this->splitForTelegram($message, self::TELEGRAM_MAX_CHARS);
        $total = count($chunks);

        $sentAll = true;
        foreach ($chunks as $i => $chunk) {
            $prefix = $total > 1 ? sprintf("[%d/%d]\n", $i + 1, $total) : '';
            try {
                $ok = $telegram->sendToUser((string) $chatId, $prefix . $chunk);
            } catch (\Throwable $e) {
                $ok = false;
                Log::error('Kunlik baho digestini yuborishda xato: ' . $e->getMessage());
            }
            if (!$ok) {
                $sentAll = false;
                $this->error('Digest qismi #' . ($i + 1) . ' yuborilmadi (laravel.log faylini tekshiring).');
                break;
            }
        }

        $stats = 'otrabotka=' . count($retakeRows)
            . ', o\'zgartirilgan=' . count($modifiedRows)
            . ', kechikib=' . count($lateCreateRows)
            . ', o\'chirilgan=' . count($deletedRows)
            . ', qism=' . $total;

        if ($sentAll) {
            $this->info('Digest yuborildi: ' . $stats);
            return self::SUCCESS;
        }

        $this->error('Digest yuborilmadi: ' . $stats);
        return self::FAILURE;
    }

    /**
     * Telegram 4096 belgi cheklovidan oshmasligi uchun xabarni qatorlar
     * chegarasida bo'laklarga ajratadi.
     */
    private function splitForTelegram(string $message, int $max): array
    {
        if (mb_strlen($message) <= $max) {
            return [$message];
        }

        $chunks = [];
        $current = '';
        // Avval ikki bo'sh qator (sektsiyalar), so'ng bitta qator chegarasida bo'lamiz.
        foreach (preg_split("/(\n\n)/", $message, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if (mb_strlen($current) + mb_strlen($part) <= $max) {
                $current .= $part;
                continue;
            }
            if ($current !== '') {
                $chunks[] = rtrim($current);
                $current = '';
            }
            if (mb_strlen($part) <= $max) {
                $current = ltrim($part, "\n");
                continue;
            }
            // Bitta sektsiya o'zi ham juda katta — qatorma-qator bo'lamiz.
            foreach (explode("\n", $part) as $line) {
                $candidate = $current === '' ? $line : $current . "\n" . $line;
                if (mb_strlen($candidate) <= $max) {
                    $current = $candidate;
                } else {
                    if ($current !== '') {
                        $chunks[] = $current;
                    }
                    $current = mb_substr($line, 0, $max);
                }
            }
        }
        if ($current !== '') {
            $chunks[] = rtrim($current);
        }
        return $chunks;
    }

    private function buildContext(ActivityLog $log, ?StudentGrade $grade, $students): array
    {
        $student = $grade && $grade->student_id ? $students->get($grade->student_id) : null;
        $studentName = $student?->full_name
            ?? ($grade?->student_hemis_id ? (string) $grade->student_hemis_id : '—');

        $lessonDate = $grade?->lesson_date
            ? Carbon::parse($grade->lesson_date)->format('d.m.Y')
            : '—';
        $lessonShort = $grade?->lesson_date
            ? Carbon::parse($grade->lesson_date)->format('d.m')
            : '—';

        return [
            'student' => $studentName,
            'subject' => $grade?->subject_name ?? '—',
            'type' => $grade?->training_type_name ?? '—',
            'lesson_date' => $lessonDate,
            'lesson_short' => $lessonShort,
            'teacher' => $grade?->employee_name ?? '—',
            'actor' => $this->shortActor($log->user_name),
            'at' => Carbon::parse($log->created_at)->setTimezone('Asia/Tashkent')->format('d.m H:i'),
        ];
    }

    private function formatMessage(array $retakeRows, array $modifiedRows, array $lateCreateRows, array $deletedRows, Carbon $since, Carbon $until): string
    {
        $header = "📊 <b>Baholar digesti</b>\n"
            . "🕒 Davr: <b>" . $since->format('d.m H:i') . " → " . $until->format('d.m H:i') . "</b>\n"
            . "♻️ Otrabotka: <b>" . count($retakeRows) . "</b>"
            . " | ✏️ O'zgartirilgan: <b>" . count($modifiedRows) . "</b>"
            . " | ⏰ Kechikib: <b>" . count($lateCreateRows) . "</b>"
            . (count($deletedRows) ? " | 🗑 O'chirilgan: <b>" . count($deletedRows) . "</b>" : '');

        $sections = [$header];

        if (!empty($retakeRows)) {
            $sections[] = $this->renderTable(
                "♻️ <b>Otrabotka baholari</b>",
                [
                    ['header' => '#',      'width' => 3,  'render' => fn ($r, $i) => (string) ($i + 1)],
                    ['header' => 'Talaba', 'width' => 22, 'render' => fn ($r) => $r['student']],
                    ['header' => 'Fan',    'width' => 18, 'render' => fn ($r) => $r['subject']],
                    ['header' => 'Sana',   'width' => 5,  'render' => fn ($r) => $r['lesson_short']],
                    ['header' => 'Baho',   'width' => 13, 'render' => function ($r) {
                        $sab = $r['was_sababli'] === null ? '' : ($r['was_sababli'] ? ' sab' : ' sabs');
                        return sprintf('%s→%s%s', $r['from'], $r['to'], $sab);
                    }],
                    ['header' => 'Kim',    'width' => 14, 'render' => fn ($r) => $r['actor']],
                ],
                $retakeRows
            );
        }

        if (!empty($modifiedRows)) {
            $sections[] = $this->renderTable(
                "✏️ <b>O'zgartirilgan baholar</b>",
                [
                    ['header' => '#',      'width' => 3,  'render' => fn ($r, $i) => (string) ($i + 1)],
                    ['header' => 'Talaba', 'width' => 22, 'render' => fn ($r) => $r['student']],
                    ['header' => 'Fan',    'width' => 18, 'render' => fn ($r) => $r['subject']],
                    ['header' => 'Sana',   'width' => 5,  'render' => fn ($r) => $r['lesson_short']],
                    ['header' => 'Baho',   'width' => 11, 'render' => fn ($r) => sprintf('%s→%s', $r['from'], $r['to'])],
                    ['header' => 'Kim',    'width' => 14, 'render' => fn ($r) => $r['actor']],
                ],
                $modifiedRows
            );
        }

        if (!empty($lateCreateRows)) {
            $sections[] = $this->renderTable(
                "⏰ <b>Kechikib qo'yilgan baholar</b>",
                [
                    ['header' => '#',      'width' => 3,  'render' => fn ($r, $i) => (string) ($i + 1)],
                    ['header' => 'Talaba', 'width' => 22, 'render' => fn ($r) => $r['student']],
                    ['header' => 'Fan',    'width' => 18, 'render' => fn ($r) => $r['subject']],
                    ['header' => 'Sana',   'width' => 5,  'render' => fn ($r) => $r['lesson_short']],
                    ['header' => 'Baho',   'width' => 5,  'render' => fn ($r) => $r['to']],
                    ['header' => 'Kech',   'width' => 6,  'render' => fn ($r) => ((int) $r['delay_days']) . 'kun'],
                    ['header' => 'Kim',    'width' => 14, 'render' => fn ($r) => $r['actor']],
                ],
                $lateCreateRows
            );
        }

        if (!empty($deletedRows)) {
            $sections[] = $this->renderTable(
                "🗑 <b>O'chirilgan baholar</b>",
                [
                    ['header' => '#',      'width' => 3,  'render' => fn ($r, $i) => (string) ($i + 1)],
                    ['header' => 'Talaba', 'width' => 22, 'render' => fn ($r) => $r['student']],
                    ['header' => 'Fan',    'width' => 18, 'render' => fn ($r) => $r['subject']],
                    ['header' => 'Sana',   'width' => 5,  'render' => fn ($r) => $r['lesson_short']],
                    ['header' => 'Baho',   'width' => 13, 'render' => function ($r) {
                        return $r['retake_grade'] === '—'
                            ? $r['grade']
                            : ($r['grade'] . ' / ot:' . $r['retake_grade']);
                    }],
                    ['header' => 'Kim',    'width' => 14, 'render' => fn ($r) => $r['actor']],
                ],
                $deletedRows
            );
        }

        return implode("\n\n", $sections);
    }

    /**
     * Sektsiya sarlavhasi + monospaced jadval (bir nechta &lt;pre&gt; bloklari)
     * tarzida render qiladi. Har bir blok belgilangan miqdorda qatordan oshmaydi,
     * shuning uchun splitForTelegram &lt;pre&gt; chegarasida toza bo'la oladi.
     */
    private function renderTable(string $title, array $columns, array $rows, int $rowsPerBlock = 12): string
    {
        if (empty($rows)) {
            return $title;
        }

        $headerCells = [];
        $separatorCells = [];
        foreach ($columns as $col) {
            $headerCells[] = $this->mbPad($col['header'], $col['width']);
            $separatorCells[] = str_repeat('-', $col['width']);
        }
        $headerLine = implode('  ', $headerCells);
        $separatorLine = implode('  ', $separatorCells);

        $blocks = [];
        $batches = array_chunk($rows, $rowsPerBlock, true);
        foreach ($batches as $batch) {
            $lines = [$headerLine, $separatorLine];
            foreach ($batch as $i => $row) {
                $cells = [];
                foreach ($columns as $col) {
                    $value = $col['render']($row, $i);
                    $cells[] = $this->mbPad((string) $value, $col['width']);
                }
                $lines[] = implode('  ', $cells);
            }
            // <pre> ichidagi har bir cell allaqachon plain matn — HTML escape qilamiz.
            $blocks[] = '<pre>' . $this->esc(implode("\n", $lines)) . '</pre>';
        }

        return $title . "\n" . implode("\n\n", $blocks);
    }

    /**
     * Multibyte-safe satrni belgilangan kenglikkacha qisqartirish yoki to'ldirish.
     * Qisqartirilganda oxiriga "…" qo'yiladi.
     */
    private function mbPad(string $str, int $width): string
    {
        // Yangi qatorlar va boshqa belgilarni bitta bo'shliqqa almashtiramiz.
        $str = preg_replace('/\s+/u', ' ', trim($str));
        $len = mb_strlen($str, 'UTF-8');
        if ($len > $width) {
            return mb_substr($str, 0, max(0, $width - 1), 'UTF-8') . '…';
        }
        return $str . str_repeat(' ', $width - $len);
    }

    private function shortActor(?string $name): string
    {
        if (empty($name)) {
            return 'system';
        }
        // "FAMILIYA Ism Otasining" → "FAMILIYA I."
        $parts = preg_split('/\s+/u', trim($name));
        if (count($parts) >= 2) {
            return $parts[0] . ' ' . mb_substr($parts[1], 0, 1, 'UTF-8') . '.';
        }
        return $name;
    }

    private function fmt($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }
        return (string) $value;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
