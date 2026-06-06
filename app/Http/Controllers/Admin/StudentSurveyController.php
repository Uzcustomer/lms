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
     * Fon'da ishga tushadi: javob darhol qaytadi, admin sahifada progress
     * polling orqali ko'rinadi.
     */
    public function sendTelegramAnnouncement(Request $request, TelegramService $telegram)
    {
        return $this->dispatchTelegramSend('announcement', $telegram);
    }

    /**
     * So'rovnomani bajarmagan talabalarga eslatma. Fon'da ishlaydi (504'ga yo'l qo'ymaydi).
     */
    public function sendTelegramReminder(Request $request, TelegramService $telegram)
    {
        return $this->dispatchTelegramSend('reminder', $telegram);
    }

    /**
     * Telegram yuborishni fon'da boshlash. fastcgi_finish_request() bilan
     * javob darhol qaytadi, qolgan jarayon PHP-FPM da davom etadi.
     */
    private function dispatchTelegramSend(string $kind, TelegramService $telegram)
    {
        $status = Setting::get('student_survey_tg_status', 'idle');
        if ($status === 'running') {
            // Stale: agar 60 daqiqadan ko'p oldin boshlangan bo'lsa, reset qilamiz
            $startedAt = Setting::get('student_survey_tg_started_at');
            $stale = $startedAt && (time() - strtotime($startedAt) > 60 * 60);
            if (!$stale) {
                return back()->with('success', "Avvalgi yuborish hali davom etmoqda — progress'ni kuting.");
            }
            Log::warning('Resetting stale telegram send', ['started_at' => $startedAt]);
        }

        // Status flagini darhol "running" qilib qo'yamiz — dublikat click'lardan himoya
        Setting::set('student_survey_tg_status', 'running');
        Setting::set('student_survey_tg_kind', $kind);
        Setting::set('student_survey_tg_total', '0');
        Setting::set('student_survey_tg_sent', '0');
        Setting::set('student_survey_tg_failed', '0');
        Setting::set('student_survey_tg_started_at', now()->toDateTimeString());

        $kindLabel = $kind === 'announcement' ? "E'lon" : "Eslatma";

        // Foydalanuvchiga darhol javob qaytarish — keyin fon'da davom etamiz
        $response = back()->with('success', "{$kindLabel} fon'da yuborilmoqda. Progress sahifadan kuzatish mumkin.")
            ->setStatusCode(303);

        // Brauzerga javob yuborish va ulanishni yopish
        if (function_exists('fastcgi_finish_request')) {
            // Bu PHP-FPM ostida ishlatiladi (nginx + php-fpm)
            $response->send();
            session()->save();
            fastcgi_finish_request();
        } else {
            // Fallback — flush + ignore_user_abort
            $response->send();
            if (session()->isStarted()) session()->save();
            @ob_end_flush();
            @flush();
        }

        // Endi fon'da ishlaymiz — admin allaqachon sahifani ko'rmoqda
        ignore_user_abort(true);
        @set_time_limit(0);

        try {
            $this->runTelegramSend($kind, $telegram);
        } catch (\Throwable $e) {
            Log::error('Survey telegram background send failed', ['error' => $e->getMessage(), 'kind' => $kind]);
            Setting::set('student_survey_tg_status', 'failed');
            Setting::set('student_survey_tg_last_error', mb_substr($e->getMessage(), 0, 500));
        }

        // PHP-FPM ostida bu yerga javob yetib bormaydi (client allaqachon yopilgan),
        // lekin testlash uchun qaytaramiz.
        return $response;
    }

    /**
     * Asosiy yuborish jarayoni — chunklar bilan, har 50 talabadan keyin progress yangilanadi.
     */
    private function runTelegramSend(string $kind, TelegramService $telegram): void
    {
        $config = config('student_survey');
        $surveyKey = $config['key'];
        $deadlineFormatted = \Carbon\Carbon::parse(self::currentDeadline())->format('d.m.Y H:i');

        $query = Student::query()
            ->where('student_status_code', 11)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '');

        if ($kind === 'reminder') {
            $completedIds = StudentSurveyCompletion::where('survey_key', $surveyKey)
                ->pluck('student_hemis_id')
                ->all();
            if (!empty($completedIds)) {
                $query->whereNotIn('hemis_id', $completedIds);
            }
        }

        $total = (clone $query)->count();
        Setting::set('student_survey_tg_total', (string) $total);

        // 3 tilda matnlar oldindan
        $titleArr = $config['title'] ?? [];
        $messages = [];
        foreach (['uz', 'ru', 'en'] as $loc) {
            $title = sv_t($titleArr, $loc);
            $messages[$loc] = $kind === 'announcement'
                ? $this->buildAnnouncementMessage($title, $deadlineFormatted, $loc)
                : $this->buildReminderMessage($title, $deadlineFormatted, $loc);
        }

        $sent = 0;
        $failed = 0;
        $i = 0;

        $query->select(['id', 'hemis_id', 'telegram_chat_id', 'department_name'])
            ->chunkById(200, function ($chunk) use (&$sent, &$failed, &$i, $telegram, $messages) {
                foreach ($chunk as $student) {
                    $loc = self::detectStudentLocale($student);
                    $ok = $telegram->sendToUser((string) $student->telegram_chat_id, $messages[$loc]);
                    if ($ok) $sent++; else $failed++;
                    $i++;
                    // Har 25 talabadan keyin progress yozish — DB'ga ortiqcha yozish bo'lmasin
                    if ($i % 25 === 0) {
                        Setting::set('student_survey_tg_sent', (string) $sent);
                        Setting::set('student_survey_tg_failed', (string) $failed);
                    }
                    usleep(50_000);
                }
            }, 'id');

        // Yakuniy holatni yozish
        Setting::set('student_survey_tg_sent', (string) $sent);
        Setting::set('student_survey_tg_failed', (string) $failed);
        Setting::set('student_survey_tg_status', 'done');
        Setting::set('student_survey_tg_finished_at', now()->toDateTimeString());

        Log::info('Survey telegram bulk send finished', [
            'kind'   => $kind,
            'total'  => $i,
            'sent'   => $sent,
            'failed' => $failed,
        ]);
    }

    /**
     * Progress holatini AJAX uchun JSON qaytaradi.
     */
    public function telegramStatus()
    {
        return response()->json([
            'status'   => Setting::get('student_survey_tg_status', 'idle'),
            'kind'     => Setting::get('student_survey_tg_kind'),
            'total'    => (int) Setting::get('student_survey_tg_total', 0),
            'sent'     => (int) Setting::get('student_survey_tg_sent', 0),
            'failed'   => (int) Setting::get('student_survey_tg_failed', 0),
            'started_at'  => Setting::get('student_survey_tg_started_at'),
            'finished_at' => Setting::get('student_survey_tg_finished_at'),
            'last_error'  => Setting::get('student_survey_tg_last_error'),
        ]);
    }

    /**
     * Talabaning fakulteti nomidan tilni aniqlash — xalqaro fakultet → en, aks holda uz.
     */
    public static function detectStudentLocale($student): string
    {
        $dept = mb_strtolower((string) ($student->department_name ?? ''));
        if (str_contains($dept, 'xalqaro') || str_contains($dept, 'international') || str_contains($dept, 'международ')) {
            return 'en';
        }
        return 'uz';
    }

    /**
     * Har talabaga uning tiliga mos xabar yuborish.
     *
     * @return array{0:int,1:int}
     */
    private function bulkSendLocalized(TelegramService $telegram, $students, $titleArr, string $deadlineFormatted, string $kind): array
    {
        // 3 ta tilda matnlarni oldindan tayyorlab qo'yish
        $messages = [];
        foreach (['uz', 'ru', 'en'] as $loc) {
            $title = sv_t($titleArr, $loc);
            $messages[$loc] = $kind === 'announcement'
                ? $this->buildAnnouncementMessage($title, $deadlineFormatted, $loc)
                : $this->buildReminderMessage($title, $deadlineFormatted, $loc);
        }

        $sent = 0;
        $failed = 0;
        foreach ($students as $student) {
            $loc = self::detectStudentLocale($student);
            $ok = $telegram->sendToUser((string) $student->telegram_chat_id, $messages[$loc]);
            if ($ok) $sent++; else $failed++;
            usleep(50_000);
        }
        return [$sent, $failed];
    }

    /**
     * Bir guruh talabaga bitta xabar yuborish (eski signature — boshqa joyda qolgan bo'lsa).
     *
     * @return array{0:int,1:int}
     */
    private function bulkSend(TelegramService $telegram, $students, string $message): array
    {
        $sent = 0;
        $failed = 0;
        foreach ($students as $student) {
            $ok = $telegram->sendToUser((string) $student->telegram_chat_id, $message);
            if ($ok) $sent++; else $failed++;
            usleep(50_000);
        }
        return [$sent, $failed];
    }

    public function buildAnnouncementMessage(string $title, string $deadlineFormatted, string $locale = 'uz'): string
    {
        $tpl = [
            'uz' => [
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
                "Tizimga kirish: https://mark.tashmedunitf.uz",
            ],
            'ru' => [
                "📣 <b>Опрос начался</b>",
                "",
                "<b>Тема:</b> {$title}",
                "",
                "Уважаемый студент! В нашем университете проводится короткий опрос для улучшения работы офиса Регистратора и экзаменационных процессов. Ваше мнение поможет нам в принятии решений.",
                "",
                "⏰ <b>Срок окончания:</b> {$deadlineFormatted}",
                "",
                "⚠️ <b>Внимание:</b> После истечения срока студенты, не прошедшие опрос, не смогут пользоваться сервисами платформы — вход в профиль будет закрыт.",
                "",
                "🔒 <b>Анонимность:</b> Ваши ответы полностью конфиденциальны и никому не видны. Данные используются только для общей статистики. Пожалуйста, отвечайте искренне и беспристрастно.",
                "",
                "Войти в систему: https://mark.tashmedunitf.uz",
            ],
            'en' => [
                "📣 <b>Survey is now open</b>",
                "",
                "<b>Topic:</b> {$title}",
                "",
                "Dear student! Our university is conducting a short survey to improve the Registration Office services and exam processes. Your opinion will help us make better decisions.",
                "",
                "⏰ <b>Deadline:</b> {$deadlineFormatted}",
                "",
                "⚠️ <b>Attention:</b> Once the deadline passes, students who haven't completed the survey will lose access to platform services — profile entry will be blocked.",
                "",
                "🔒 <b>Anonymity:</b> Your answers are kept completely private and are not shown to anyone. The data is used only for aggregate statistics. Please answer sincerely and impartially.",
                "",
                "Open the platform: https://mark.tashmedunitf.uz",
            ],
        ];

        return implode("\n", $tpl[$locale] ?? $tpl['uz']);
    }

    public function buildReminderMessage(string $title, string $deadlineFormatted, string $locale = 'uz'): string
    {
        $tpl = [
            'uz' => [
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
                "Tizimga kirish: https://mark.tashmedunitf.uz",
            ],
            'ru' => [
                "🔔 <b>Напоминание — опрос для студентов</b>",
                "",
                "<b>Тема:</b> {$title}",
                "",
                "Уважаемый студент, вы ещё не прошли опрос. Пожалуйста, уделите немного времени, войдите в систему и заполните его.",
                "",
                "⏰ <b>Срок окончания:</b> {$deadlineFormatted}",
                "",
                "⚠️ <b>Внимание:</b> После истечения срока студенты, не прошедшие опрос, не смогут войти в профиль.",
                "",
                "🔒 <b>Конфиденциальность:</b> Ваши ответы полностью анонимны и никому не видны — данные используются только для общей статистики. Пожалуйста, отвечайте искренне.",
                "",
                "Войти в систему: https://mark.tashmedunitf.uz",
            ],
            'en' => [
                "🔔 <b>Reminder — Student survey</b>",
                "",
                "<b>Topic:</b> {$title}",
                "",
                "Dear student, you haven't completed the survey yet. Please take a few minutes to log in and fill it out.",
                "",
                "⏰ <b>Deadline:</b> {$deadlineFormatted}",
                "",
                "⚠️ <b>Attention:</b> After the deadline, students who haven't completed the survey won't be able to access their profile.",
                "",
                "🔒 <b>Privacy:</b> Your answers are completely anonymous and not shown to anyone — the data is used only for aggregate statistics. Please answer sincerely and impartially.",
                "",
                "Open the platform: https://mark.tashmedunitf.uz",
            ],
        ];

        return implode("\n", $tpl[$locale] ?? $tpl['uz']);
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
