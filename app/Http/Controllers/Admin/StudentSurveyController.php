<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSurveyAnswer;
use App\Models\StudentSurveyCompletion;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin tomoni — talabalar so'rovnomasi natijalarini ko'rish va Telegramga
 * eslatma yuborish.
 */
class StudentSurveyController extends Controller
{
    /**
     * Survey faolligi — admin toggle. Off bo'lsa talabaga banner/popup ko'rinmaydi,
     * middleware ham bloklamaydi, submit ham qabul qilinmaydi.
     */
    public static function isActive(): bool
    {
        return Setting::get('student_survey_active', '0') === '1';
    }

    /**
     * Joriy deadline — Setting bo'lsa undan, bo'lmasa config faylidan.
     * Format: 'Y-m-d H:i:s'.
     */
    public static function currentDeadline(): string
    {
        $override = Setting::get('student_survey_deadline');
        if ($override) return $override;
        return (string) config('student_survey.deadline');
    }

    public function index()
    {
        $config = config('student_survey');
        $config['deadline'] = self::currentDeadline();
        $surveyKey = $config['key'];

        $totalActive = Student::where('student_status_code', 11)->count();
        $completedCount = StudentSurveyCompletion::where('survey_key', $surveyKey)->count();
        $pendingCount = max(0, $totalActive - $completedCount);

        // Har bir savol uchun taqsimot
        $stats = [];
        foreach ($config['questions'] as $q) {
            $stats[$q['id']] = $this->statsForQuestion($surveyKey, $q);
        }

        // Erkin matnli javoblar (text turidagi savollar)
        $textAnswers = [];
        foreach ($config['questions'] as $q) {
            if (($q['type'] ?? '') !== 'text') continue;
            $rows = StudentSurveyAnswer::query()
                ->where('survey_key', $surveyKey)
                ->where('question_id', (string) $q['id'])
                ->whereNotNull('answer')
                ->where('answer', '!=', '')
                ->latest('id')
                ->limit(200)
                ->get(['answer', 'created_at', 'student_hemis_id']);
            $textAnswers[$q['id']] = $rows;
        }

        // Fakultet bo'yicha qoplama
        $facultyStats = StudentSurveyCompletion::query()
            ->where('survey_key', $surveyKey)
            ->join('students', 'students.hemis_id', '=', 'student_survey_completions.student_hemis_id')
            ->selectRaw('students.department_name as fakultet, COUNT(*) as soni')
            ->groupBy('students.department_name')
            ->orderByDesc('soni')
            ->get();

        $deadlineFormatted = \Carbon\Carbon::parse($config['deadline'])->format('d.m.Y H:i');
        $deadlinePassed = strtotime($config['deadline']) < time();
        $deadlineForInput = \Carbon\Carbon::parse($config['deadline'])->format('Y-m-d\TH:i');

        return view('admin.student-survey.index', [
            'config'           => $config,
            'isActive'         => self::isActive(),
            'deadlineForInput' => $deadlineForInput,
            'totalActive'      => $totalActive,
            'completedCount'   => $completedCount,
            'pendingCount'     => $pendingCount,
            'stats'            => $stats,
            'textAnswers'      => $textAnswers,
            'facultyStats'     => $facultyStats,
            'deadlineFormatted' => $deadlineFormatted,
            'deadlinePassed'   => $deadlinePassed,
        ]);
    }

    /**
     * Deadline'ni admin tomondan o'zgartirish.
     * Format: ISO-like "Y-m-d\TH:i" (HTML datetime-local input).
     */
    public function updateDeadline(Request $request)
    {
        $request->validate([
            'deadline' => 'required|date',
        ]);

        $dt = \Carbon\Carbon::parse($request->input('deadline'))->format('Y-m-d H:i:s');
        Setting::set('student_survey_deadline', $dt);

        Log::info('Student survey deadline updated', ['deadline' => $dt, 'by' => auth()->id()]);

        return back()->with('success', "Yangi tugash muddati saqlandi: " . \Carbon\Carbon::parse($dt)->format('d.m.Y H:i'));
    }

    /**
     * Survey faollik holatini almashtirish (admin toggle).
     */
    public function toggleActive(Request $request)
    {
        $enabled = $request->boolean('enabled');
        Setting::set('student_survey_active', $enabled ? '1' : '0');

        Log::info('Student survey toggled', ['enabled' => $enabled, 'by' => auth()->id()]);

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled
                ? "So'rovnoma yoqildi — talabalar uchun ko'rinadi."
                : "So'rovnoma o'chirildi — talabalarga ko'rinmaydi.",
        ]);
    }

    /**
     * Barcha faol talabalarga so'rovnoma boshlangani haqida e'lon — bir marta.
     */
    public function sendTelegramAnnouncement(Request $request, TelegramService $telegram)
    {
        $config = config('student_survey');
        $deadlineFormatted = \Carbon\Carbon::parse($config['deadline'])->format('d.m.Y H:i');
        $message = $this->buildAnnouncementMessage($config['title'], $deadlineFormatted);

        $students = Student::query()
            ->where('student_status_code', 11)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->select(['hemis_id', 'telegram_chat_id'])
            ->get();

        [$sent, $failed] = $this->bulkSend($telegram, $students, $message);

        Log::info('Student survey announcement sent', [
            'survey_key' => $config['key'],
            'total'      => $students->count(),
            'sent'       => $sent,
            'failed'     => $failed,
        ]);

        return back()->with('success', "E'lon yuborildi: {$sent} ta. Xato: {$failed} ta. Jami: " . $students->count());
    }

    /**
     * Hozircha so'rovnomani bajarmagan, telegrami tasdiqlangan talabalarga
     * eslatma yuborish (so'rov mavzusi, deadline, anonimlik, ogohlantirish).
     */
    public function sendTelegramReminder(Request $request, TelegramService $telegram)
    {
        $config = config('student_survey');
        $surveyKey = $config['key'];

        $deadlineFormatted = \Carbon\Carbon::parse($config['deadline'])->format('d.m.Y H:i');

        $completedIds = StudentSurveyCompletion::where('survey_key', $surveyKey)
            ->pluck('student_hemis_id')
            ->all();

        $pending = Student::query()
            ->where('student_status_code', 11)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->when(!empty($completedIds), fn($q) => $q->whereNotIn('hemis_id', $completedIds))
            ->select(['hemis_id', 'telegram_chat_id'])
            ->get();

        $message = $this->buildReminderMessage($config['title'], $deadlineFormatted);
        [$sent, $failed] = $this->bulkSend($telegram, $pending, $message);

        Log::info('Student survey telegram reminder sent', [
            'survey_key' => $surveyKey,
            'pending'    => $pending->count(),
            'sent'       => $sent,
            'failed'     => $failed,
        ]);

        return back()->with('success', "Eslatma yuborildi: {$sent} ta. Xato: {$failed} ta. Jami bajarmaganlar: " . $pending->count());
    }

    /**
     * Bir guruh talabaga xabar yuborish — Telegram bot limiti uchun mikro-pauza bilan.
     *
     * @return array{0:int,1:int} [sent, failed]
     */
    private function bulkSend(TelegramService $telegram, $students, string $message): array
    {
        $sent = 0;
        $failed = 0;
        foreach ($students as $student) {
            $ok = $telegram->sendToUser((string) $student->telegram_chat_id, $message);
            if ($ok) $sent++; else $failed++;
            usleep(50_000); // ~20 msg/sec — bot API limiti uchun xavfsiz
        }
        return [$sent, $failed];
    }

    public function buildAnnouncementMessage(string $title, string $deadlineFormatted): string
    {
        $lines = [
            "📣 <b>So'rovnoma boshlandi</b>",
            "",
            "<b>Mavzu:</b> {$title}",
            "",
            "Hurmatli talaba! Universitetimizning Registrator ofisi xizmati va imtihon jarayonlarini yaxshilash bo'yicha qisqa so'rovnoma o'tkazilmoqda. Sizning fikringiz bizga qaror qabul qilishda yordam beradi.",
            "",
            "⏰ <b>Tugash muddati:</b> {$deadlineFormatted}",
            "",
            "⚠️ <b>Diqqat:</b> Muddat tugagandan keyin so'rovnomani bajarmagan talabalar tizim xizmatlaridan foydalana olmaydi — profilga kira olmaydi.",
            "",
            "🔒 <b>Anonimlik:</b> Javoblaringiz mutlaqo yashirin saqlanadi va hech kimga ko'rinmaydi. Ma'lumotlar faqat umumiy statistika uchun ishlatiladi. Iltimos, samimiy va xolis javob bering.",
            "",
            "Tizimga kirish: https://lms.tashmedunitf.uz",
        ];

        return implode("\n", $lines);
    }

    public function buildReminderMessage(string $title, string $deadlineFormatted): string
    {
        $lines = [
            "🔔 <b>Eslatma — Talabalar so'rovnomasi</b>",
            "",
            "<b>Mavzu:</b> {$title}",
            "",
            "Hurmatli talaba, hozirgacha so'rovnomani to'ldirmagansiz. Iltimos, qisqa vaqt ajratib, tizimga kirib so'rovnomani bajaring.",
            "",
            "⏰ <b>Tugash muddati:</b> {$deadlineFormatted}",
            "",
            "⚠️ <b>Diqqat:</b> Muddat o'tgandan keyin so'rovnomani bajarmagan talabalar profilga kira olmaydi.",
            "",
            "🔒 <b>Shaxsiy ma'lumotlaringiz yashirin:</b> Javoblaringiz mutlaqo anonim hisoblanadi va hech kimga ko'rinmaydi — ma'lumotlar faqat umumiy statistika uchun ishlatiladi. Iltimos, samimiy va xolis javob bering.",
            "",
            "Tizimga kirish: https://lms.tashmedunitf.uz",
        ];

        return implode("\n", $lines);
    }

    /**
     * Bitta savol bo'yicha javoblar taqsimotini hisoblash.
     * Radio uchun — answer ustuni, checkbox uchun — answer_multi (JSON).
     * "other:" prefiksli javoblar alohida sanaladi.
     */
    private function statsForQuestion(string $surveyKey, array $q): array
    {
        $type = $q['type'];
        if ($type === 'text') {
            // Erkin matn — kiritilgan javoblar soni
            $count = StudentSurveyAnswer::where('survey_key', $surveyKey)
                ->where('question_id', (string) $q['id'])
                ->whereNotNull('answer')
                ->where('answer', '!=', '')
                ->count();
            return ['type' => 'text', 'count' => $count];
        }

        $optionsById = [];
        foreach ($q['options'] ?? [] as $opt) {
            $optionsById[$opt['id']] = sv_t($opt['text'], 'uz');
        }

        $totals = array_fill_keys(array_keys($optionsById), 0);
        $otherTexts = [];

        $rows = StudentSurveyAnswer::where('survey_key', $surveyKey)
            ->where('question_id', (string) $q['id'])
            ->get(['answer', 'answer_multi']);

        $responders = 0;

        foreach ($rows as $row) {
            if ($type === 'radio') {
                $val = $row->answer;
                if (!is_string($val) || $val === '') continue;
                $responders++;
                if (str_starts_with($val, 'other:')) {
                    $totals['other'] = ($totals['other'] ?? 0) + 1;
                    $otherTexts[] = trim(substr($val, 6));
                } elseif (isset($totals[$val])) {
                    $totals[$val]++;
                }
            } else { // checkbox
                $vals = $row->answer_multi;
                if (!is_array($vals) || count($vals) === 0) continue;
                $responders++;
                foreach ($vals as $v) {
                    if (is_string($v) && str_starts_with($v, 'other:')) {
                        $totals['other'] = ($totals['other'] ?? 0) + 1;
                        $otherTexts[] = trim(substr($v, 6));
                    } elseif (is_string($v) && isset($totals[$v])) {
                        $totals[$v]++;
                    }
                }
            }
        }

        return [
            'type'       => $type,
            'totals'     => $totals,
            'options'    => $optionsById,
            'responders' => $responders,
            'otherTexts' => $otherTexts,
        ];
    }
}
