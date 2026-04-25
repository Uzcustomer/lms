<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\TableImageGenerator;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyGradeChangesDigest extends Command
{
    protected $signature = 'grades:send-daily-changes-digest
                            {--hours=24 : Oxirgi necha soatlik ozgarishlarni olish}
                            {--late-days=2 : Necha kun kech kiritilgan baho "kechikib qoyilgan" deb hisoblanadi}';

    protected $description = "Admin chatiga kechagi sutkadagi otrabotka va o'zgartirilgan baholar jadvalini rasm sifatida yuboradi";

    private const RETAKE_FIELDS = [
        'retake_grade',
        'retake_graded_at',
        'retake_file_path',
        'retake_by',
    ];

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

        $period = $since->format('d.m H:i') . ' → ' . $until->format('d.m H:i');
        $summary = "📊 <b>Baholar digesti</b>\n"
            . "🕒 Davr: <b>" . $period . "</b>\n"
            . "♻️ Otrabotka: <b>" . count($retakeRows) . "</b>"
            . " | ✏️ O'zgartirilgan: <b>" . count($modifiedRows) . "</b>"
            . " | ⏰ Kechikib: <b>" . count($lateCreateRows) . "</b>"
            . (count($deletedRows) ? " | 🗑 O'chirilgan: <b>" . count($deletedRows) . "</b>" : '');

        $tempFiles = [];
        $sentAll = true;

        try {
            if (!$telegram->sendToUser((string) $chatId, $summary)) {
                $this->error('Digest sarlavhasi yuborilmadi.');
                return self::FAILURE;
            }

            $generator = (new TableImageGenerator())->compact();

            $sections = [
                [
                    'rows' => $retakeRows,
                    'title' => "Otrabotka baholari — {$period}",
                    'caption' => "♻️ Otrabotka baholari (" . count($retakeRows) . " ta)",
                    'headers' => ['#', 'TALABA', 'FAN', 'TURI', 'DARS', 'ESKI', 'YANGI', 'SABAB', 'KIM', 'VAQT'],
                    'rowBuilder' => function (array $r, int $i): array {
                        $sab = $r['was_sababli'] === null ? '-' : ($r['was_sababli'] ? 'sababli' : 'sababsiz');
                        return [
                            $i + 1,
                            TableImageGenerator::truncate($r['student'], 28),
                            TableImageGenerator::truncate($r['subject'], 24),
                            TableImageGenerator::truncate($r['type'], 14),
                            $r['lesson_date'],
                            $r['from'],
                            $r['to'],
                            $sab,
                            TableImageGenerator::truncate($r['actor'], 22),
                            $r['at'],
                        ];
                    },
                ],
                [
                    'rows' => $modifiedRows,
                    'title' => "O'zgartirilgan baholar — {$period}",
                    'caption' => "✏️ O'zgartirilgan baholar (" . count($modifiedRows) . " ta)",
                    'headers' => ['#', 'TALABA', 'FAN', 'TURI', 'DARS', 'ESKI', 'YANGI', 'KIM', 'VAQT'],
                    'rowBuilder' => fn (array $r, int $i): array => [
                        $i + 1,
                        TableImageGenerator::truncate($r['student'], 28),
                        TableImageGenerator::truncate($r['subject'], 24),
                        TableImageGenerator::truncate($r['type'], 14),
                        $r['lesson_date'],
                        $r['from'],
                        $r['to'],
                        TableImageGenerator::truncate($r['actor'], 22),
                        $r['at'],
                    ],
                ],
                [
                    'rows' => $lateCreateRows,
                    'title' => "Kechikib qo'yilgan baholar — {$period}",
                    'caption' => "⏰ Kechikib qo'yilgan baholar (" . count($lateCreateRows) . " ta)",
                    'headers' => ['#', 'TALABA', 'FAN', 'TURI', 'DARS', 'BAHO', 'KECH (KUN)', 'KIM', 'YARATILDI'],
                    'rowBuilder' => fn (array $r, int $i): array => [
                        $i + 1,
                        TableImageGenerator::truncate($r['student'], 28),
                        TableImageGenerator::truncate($r['subject'], 24),
                        TableImageGenerator::truncate($r['type'], 14),
                        $r['lesson_date'],
                        $r['to'],
                        (int) $r['delay_days'],
                        TableImageGenerator::truncate($r['actor'], 22),
                        $r['at'],
                    ],
                ],
                [
                    'rows' => $deletedRows,
                    'title' => "O'chirilgan baholar — {$period}",
                    'caption' => "🗑 O'chirilgan baholar (" . count($deletedRows) . " ta)",
                    'headers' => ['#', 'TALABA', 'FAN', 'TURI', 'DARS', 'BAHO', 'OTRABOTKA', 'KIM', 'VAQT'],
                    'rowBuilder' => fn (array $r, int $i): array => [
                        $i + 1,
                        TableImageGenerator::truncate($r['student'], 28),
                        TableImageGenerator::truncate($r['subject'], 24),
                        TableImageGenerator::truncate($r['type'], 14),
                        $r['lesson_date'],
                        $r['grade'],
                        $r['retake_grade'],
                        TableImageGenerator::truncate($r['actor'], 22),
                        $r['at'],
                    ],
                ],
            ];

            foreach ($sections as $section) {
                if (empty($section['rows'])) {
                    continue;
                }
                $tableRows = [];
                foreach (array_values($section['rows']) as $i => $row) {
                    $tableRows[] = ($section['rowBuilder'])($row, $i);
                }
                $images = $generator->generate($section['headers'], $tableRows, $section['title']);
                $pageCount = count($images);
                foreach ($images as $idx => $imagePath) {
                    $tempFiles[] = $imagePath;
                    $caption = $section['caption'];
                    if ($pageCount > 1) {
                        $caption .= ' — ' . ($idx + 1) . '/' . $pageCount . '-sahifa';
                    }
                    if (!$telegram->sendPhoto((string) $chatId, $imagePath, $caption)) {
                        $sentAll = false;
                        $this->error("Rasm yuborilmadi: {$section['caption']}");
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Kunlik baho digestini yuborishda xato: ' . $e->getMessage());
            $this->error('Digest yuborishda xato: ' . $e->getMessage());
            $sentAll = false;
        } finally {
            foreach ($tempFiles as $tmp) {
                @unlink($tmp);
            }
        }

        $stats = 'otrabotka=' . count($retakeRows)
            . ', o\'zgartirilgan=' . count($modifiedRows)
            . ', kechikib=' . count($lateCreateRows)
            . ', o\'chirilgan=' . count($deletedRows);

        if ($sentAll) {
            $this->info('Digest yuborildi: ' . $stats);
            return self::SUCCESS;
        }

        $this->error('Digest qisman/yuborilmadi: ' . $stats);
        return self::FAILURE;
    }

    private function buildContext(ActivityLog $log, ?StudentGrade $grade, $students): array
    {
        $student = $grade && $grade->student_id ? $students->get($grade->student_id) : null;
        $studentName = $student?->full_name
            ?? ($grade?->student_hemis_id ? (string) $grade->student_hemis_id : '—');

        $lessonDate = $grade?->lesson_date
            ? Carbon::parse($grade->lesson_date)->format('d.m.Y')
            : '—';

        return [
            'student' => $studentName,
            'subject' => $grade?->subject_name ?? '—',
            'type' => $grade?->training_type_name ?? '—',
            'lesson_date' => $lessonDate,
            'teacher' => $grade?->employee_name ?? '—',
            'actor' => $log->user_name ?: 'system',
            'at' => Carbon::parse($log->created_at)->setTimezone('Asia/Tashkent')->format('d.m H:i'),
        ];
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
}
