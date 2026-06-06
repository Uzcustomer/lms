<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
    public function index()
    {
        $config = config('student_survey');
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

        return view('admin.student-survey.index', [
            'config'           => $config,
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
            ->select(['hemis_id', 'full_name', 'telegram_chat_id'])
            ->get();

        $title = $config['title'];
        $message = $this->buildReminderMessage($title, $deadlineFormatted);

        $sent = 0;
        $failed = 0;
        foreach ($pending as $student) {
            $ok = $telegram->sendToUser((string) $student->telegram_chat_id, $message);
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
            // Telegram bot API limiti — sekundiga ~30 xabar. Ehtiyot uchun mikro-pauza.
            usleep(50_000);
        }

        Log::info('Student survey telegram reminder sent', [
            'survey_key' => $surveyKey,
            'pending'    => $pending->count(),
            'sent'       => $sent,
            'failed'     => $failed,
        ]);

        return back()->with('success', "Telegramga yuborildi: {$sent} ta. Xato: {$failed} ta. Jami so'rovnomani bajarmaganlar: " . $pending->count());
    }

    private function buildReminderMessage(string $title, string $deadlineFormatted): string
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
            $optionsById[$opt['id']] = $opt['text'];
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
