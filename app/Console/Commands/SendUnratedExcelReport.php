<?php

namespace App\Console\Commands;

use App\Exports\LessonOpeningTeacherReportExport;
use App\Models\LessonOpening;
use App\Models\StaffRegistrationDivision;
use App\Services\LessonOpeningTeacherReport;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Har kuni ertalab "Dars ochish so'rovlari" sahifasidagi Excel hisobotni
 * (o'qituvchilar / kunlar / talabalar kesimida baho qo'yilmaganlar) qaytadan
 * hisoblab, baho qo'yilmaganlar hisoboti boradigan Telegram guruh/mavzularga
 * fayl qilib yuboradi.
 *
 * Oraliq: TELEGRAM_UNRATED_EXCEL_FROM (Y-m-d) dan kechagacha — bugun
 * hisoblanmaydi. Sozlanmagan bo'lsa kuzgi semestrda 14-sentabrdan,
 * bahorgi semestrda semestr boshidan.
 */
class SendUnratedExcelReport extends Command
{
    protected $signature = 'registrar:send-unrated-excel
        {--chat-id= : Test uchun bitta manzil ("chat" yoki "chat:mavzu") — faqat shunga yuboriladi}
        {--from= : Boshlanish sanasi (Y-m-d), sozlamadan ustun}';

    protected $description = "Baho qo'yilmaganlar Excel hisobotini (dars ochish) har kuni Telegram guruh/mavzuga yuboradi";

    public function handle(LessonOpeningTeacherReport $report, TelegramService $telegram): int
    {
        @ini_set('memory_limit', '1024M');

        $targets = $this->targets();
        if (empty($targets)) {
            $this->error('Telegram manzili sozlanmagan (TELEGRAM_REGISTRAR_GROUP_ID / TELEGRAM_UNRATED_REPORT_CHAT_IDS).');

            return self::FAILURE;
        }

        $from = $this->startDate();
        $to = now('Asia/Tashkent')->subDay()->toDateString();
        if ($from > $to) {
            $this->warn("Boshlanish sanasi ({$from}) kechagi kundan keyin — yuboriladigan hisobot yo'q.");

            return self::SUCCESS;
        }

        $this->info("Hisoblanmoqda: {$from} — {$to}");
        $started = microtime(true);
        $data = $report->build($from, $to);
        $timings = ($data['timings'] ?? []) + ['build_total' => round(microtime(true) - $started, 2)];
        $started = microtime(true);

        $relative = "exports/unrated_excel/baho-qoyilmaganlar_{$from}_{$to}.xlsx";
        Excel::store(new LessonOpeningTeacherReportExport($data), $relative, 'local');
        $path = Storage::disk('local')->path($relative);
        $timings['excel'] = round(microtime(true) - $started, 2);
        $timings['student_rows'] = count($data['students'] ?? []);
        $timings['day_rows'] = count($data['days'] ?? []);
        $started = microtime(true);

        $header = "📊 Baho qo'yilmaganlar hisoboti (Excel)\n"
            . '📅 ' . Carbon::parse($from)->format('d.m.Y') . ' — ' . Carbon::parse($to)->format('d.m.Y') . "\n"
            . "👨‍🏫 O'qituvchilar: " . count($data['teachers'] ?? []) . "\n"
            . "📆 Baholanmagan dars kunlari: " . count($data['days'] ?? []);

        // Mas'ul back ofis xodimlari kesimida — nechta talabaga baho qo'yilmagan
        $managers = $this->managerSummary($data['students'] ?? []);
        $full = $managers !== '' ? $header . "\n\n" . $managers : $header;
        // Telegram fayl izohi 1024 belgigacha — sig'masa ro'yxat alohida xabar bo'ladi
        $fits = mb_strlen($full) <= 1000;

        $sent = 0;
        foreach ($targets as $target) {
            if ($telegram->sendDocument($target, $path, $fits ? $full : $header)) {
                $sent++;
                if (!$fits && $managers !== '') {
                    $telegram->notifyChat($target, $managers);
                }
                $this->info("✓ {$target}");
            } else {
                $this->error("✕ {$target} — yuborilmadi (laravel.log ga qarang)");
            }
        }
        $this->line($managers);

        @unlink($path);
        $timings['send'] = round(microtime(true) - $started, 2);

        // Qaysi bosqich sekin ekanini ko'rish uchun (soniya / qator soni)
        $labels = [
            'slots' => "Dars jadvali (o'tgan darslar), s", 'slot_rows' => 'Jadval qatorlari',
            'grades' => 'Baholar so\'rovi, s', 'grade_rows' => 'Baho qatorlari',
            'roster' => 'Talabalar ro\'yxati, s', 'analyze' => 'Baholarni tahlil (jami), s',
            'assemble' => 'Hisobotni yig\'ish, s', 'build_total' => 'Hisob jami, s',
            'excel' => 'Excel yozish, s', 'student_rows' => 'Talabalar varag\'i qatorlari',
            'day_rows' => 'Kunlar varag\'i qatorlari', 'send' => 'Telegramga yuborish, s',
        ];
        $this->table(['Bosqich', 'Qiymat'], collect($labels)
            ->filter(fn ($label, $key) => array_key_exists($key, $timings))
            ->map(fn ($label, $key) => [$label, $timings[$key]])
            ->values()->all());
        Log::info('[UnratedExcel] Yuborildi', ['from' => $from, 'to' => $to, 'sent' => $sent, 'targets' => count($targets), 'timings' => $timings]);

        return $sent > 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Baho qo'yilmagan talabalar mas'ul back ofis xodimlari kesimida.
     *
     * Mas'ul "Registrator bo'linmalari" dagi faol back ofis biriktirmasidan
     * talabaning fakulteti, yo'nalishi va kursi bo'yicha topiladi
     * (StaffRegistrationDivision::findForStudent — talaba kartasidagi bilan
     * bir xil). Talaba bir necha darsda baholanmagan bo'lsa ham bir marta sanaladi.
     * Dars kunlari "Kunlar" varag'i qatorlari bilan bir xil birlikda: guruh + fan
     * + sana + o'qituvchi (yig'indisi umumiy "baholanmagan dars kunlari" ga teng).
     */
    private function managerSummary(array $studentRows): string
    {
        if ($studentRows === []) {
            return '';
        }

        $students = DB::table('students')
            ->whereIn('hemis_id', array_values(array_unique(array_column($studentRows, 'student_hemis_id'))))
            ->get(['hemis_id', 'department_id', 'specialty_id', 'level_code'])
            ->keyBy(fn ($row) => (string) $row->hemis_id);

        $divisions = [];   // "fakultet|yo'nalish|kurs" => biriktirma yoki null
        $byManager = [];   // teacher_id => [name, students => [hemis => true], days => [kun => true]]
        $unassigned = ['students' => [], 'days' => []];

        foreach ($studentRows as $row) {
            $hemisId = (string) $row['student_hemis_id'];
            $dayKey = $row['group'] . '|' . $row['subject'] . '|' . $row['date'] . '|' . $row['teacher'];
            $student = $students[$hemisId] ?? null;
            $division = null;
            if ($student && $student->department_id) {
                $key = $student->department_id . '|' . $student->specialty_id . '|' . $student->level_code;
                if (!array_key_exists($key, $divisions)) {
                    $divisions[$key] = StaffRegistrationDivision::findForStudent(
                        $student->department_id, $student->specialty_id, $student->level_code, 'back_office'
                    );
                }
                $division = $divisions[$key];
            }

            if (!$division) {
                $unassigned['students'][$hemisId] = true;
                $unassigned['days'][$dayKey] = true;
                continue;
            }

            $teacherId = (int) $division->teacher_id;
            if (!isset($byManager[$teacherId])) {
                $teacher = $division->teacher;
                $byManager[$teacherId] = [
                    'name' => $teacher?->full_name ?: ($teacher?->short_name ?: "Xodim #{$teacherId}"),
                    'students' => [],
                    'days' => [],
                ];
            }
            $byManager[$teacherId]['students'][$hemisId] = true;
            $byManager[$teacherId]['days'][$dayKey] = true;
        }

        uasort($byManager, fn ($a, $b) => count($b['students']) <=> count($a['students']));

        $lines = ["👥 Mas'ul back ofis xodimlari kesimida:"];
        $i = 1;
        foreach ($byManager as $manager) {
            $lines[] = $i++ . '. ' . $manager['name'] . "\n    " . count($manager['students'])
                . " ta talabaga baho qo'yilmagan · " . count($manager['days']) . ' ta dars kuni';
        }
        if ($unassigned['students']) {
            $lines[] = "⚠️ Mas'ul biriktirilmagan: " . count($unassigned['students'])
                . " ta talaba · " . count($unassigned['days']) . ' ta dars kuni';
        }

        return implode("\n", $lines);
    }

    /** Ertalabki baho qo'yilmaganlar hisoboti boradigan manzillar bilan bir xil. */
    private function targets(): array
    {
        if ($this->option('chat-id')) {
            return TelegramService::chatIds($this->option('chat-id'));
        }

        return array_values(array_unique(array_merge(
            TelegramService::chatIds(config('services.telegram.registrar_group_id')),
            TelegramService::chatIds(config('services.telegram.unrated_report_chat_ids'))
        )));
    }

    private function startDate(): string
    {
        $configured = trim((string) ($this->option('from') ?: config('services.telegram.unrated_excel_from')));
        if ($configured !== '') {
            try {
                return Carbon::parse($configured)->toDateString();
            } catch (\Throwable $e) {
                $this->warn("Noto'g'ri sana: {$configured} — standart sana olinadi.");
            }
        }

        // Kuzgi semestr (avgust–yanvar): o'quv yilining 14-sentabri; bahorgi — semestr boshi.
        $now = now('Asia/Tashkent');
        if ($now->month >= 8) {
            return $now->copy()->setDate($now->year, 9, 14)->toDateString();
        }
        if ($now->month === 1) {
            return $now->copy()->setDate($now->year - 1, 9, 14)->toDateString();
        }

        return LessonOpening::periodStart()->toDateString();
    }
}
