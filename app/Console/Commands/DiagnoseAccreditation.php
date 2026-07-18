<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Akkreditatsiya "O'zlashtirgan" ustunidagi nomutanosiblikni diagnostika qilish.
 *
 * Manba endpointlar (HEMIS OpenAPI, https://student.ttatf.uz/rest/docs.json):
 *   - GET /v1/data/academic-record-list  → "Akkreditatsiya baxolari ro'yxati"
 *     (aynan akkreditatsiya sahifasi va uning Exceli shu endpointdan oziqlanadi)
 *   - GET /v1/data/student-academic-data → bitta talabaning BARCHA akademik
 *     yozuvlari (count + items[AcademicRecord]), student_id_number/passport_pin bo'yicha.
 *
 * AcademicRecord sxemasidagi maydonlar:
 *   total_point (Ball), grade (Baho), finish_credit_status (O'zlashtirgan, bool),
 *   retraining_status (qayta o'qish, bool).
 *
 * Buyruq har bir fan bo'yicha shu xom qiymatlarni ko'rsatadi, bitta fanda
 * bir nechta yozuv (dublikat) bo'lsa belgilaydi va finish_credit_status baho
 * bilan zid bo'lgan yozuvlarni ajratadi.
 */
class DiagnoseAccreditation extends Command
{
    protected $signature = 'hemis:diagnose-accreditation
        {student : Talabaning student_id_number (afzal) yoki hemis_id}
        {--subject= : Fan nomi bo\'yicha filter (qism matn, masalan "Tibbiyot kasbiga kirish")}
        {--raw : Mos yozuvlarning xom JSON javobini chiqarish}';

    protected $description = 'Akkreditatsiyadagi O\'zlashtirgan (finish_credit_status) nomutanosibligini HEMIS API orqali tekshirish';

    public function handle(): int
    {
        $baseUrl = rtrim(config('services.hemis.base_url') ?? '', '/');
        $token = config('services.hemis.token') ?? '';

        if (!$baseUrl || !$token) {
            $this->error('HEMIS base_url yoki token topilmadi (.env: HEMIS_API_BASE_URL / HEMIS_API_TOKEN).');
            return 1;
        }

        $ident = (string) $this->argument('student');
        $subjectFilter = $this->option('subject');

        // Talabani hemis_id yoki student_id_number bo'yicha bazadan topamiz (nomini ko'rsatish uchun).
        $student = Student::where('hemis_id', $ident)->orWhere('student_id_number', $ident)->first();
        $hemisId = $student->hemis_id ?? (ctype_digit($ident) ? $ident : null);
        $studentIdNumber = $student->student_id_number ?? (ctype_digit($ident) ? null : $ident);

        if ($student) {
            $this->info("Talaba: {$student->full_name} — hemis_id={$student->hemis_id}, ID raqam={$student->student_id_number}");
        } else {
            $this->warn("Bazada talaba topilmadi — '{$ident}' to'g'ridan-to'g'ri API'ga uzatiladi.");
        }
        $this->newLine();

        // 1-usul: student-academic-data (bitta talaba, barcha yozuvlar) — student_id_number bo'yicha.
        $items = [];
        $source = null;
        if ($studentIdNumber) {
            $resp = $this->apiGet("{$baseUrl}/v1/data/student-academic-data", $token, ['student_id_number' => $studentIdNumber]);
            if ($resp !== null && is_array($resp['data']['items'] ?? null)) {
                $items = $resp['data']['items'];
                $source = 'student-academic-data (student_id_number=' . $studentIdNumber . ')';
            }
        }

        // 2-usul (fallback): academic-record-list ni _student yoki student_id_number bo'yicha sahifalab tortamiz.
        if (empty($items)) {
            $filter = $hemisId ? ['_student' => $hemisId] : ['student_id_number' => $studentIdNumber];
            $page = 1;
            do {
                $resp = $this->apiGet("{$baseUrl}/v1/data/academic-record-list", $token, $filter + ['page' => $page, 'limit' => 200]);
                if ($resp === null) {
                    return 1;
                }
                $data = $resp['data'] ?? [];
                foreach (($data['items'] ?? []) as $it) {
                    $items[] = $it;
                }
                $pageCount = (int) ($data['pagination']['pageCount'] ?? 1);
                $page++;
            } while ($page <= $pageCount);
            $source = 'academic-record-list (' . http_build_query($filter) . ')';
        }

        if (empty($items)) {
            $this->warn('Bu talaba uchun akademik yozuv qaytmadi.');
            return 0;
        }
        $this->line("Manba: {$source} — jami {$this->countLabel(count($items))} yozuv.");
        $this->newLine();

        if ($subjectFilter) {
            $items = array_values(array_filter($items, fn($it) => mb_stripos((string) ($it['subject_name'] ?? ''), $subjectFilter) !== false));
            if (empty($items)) {
                $this->warn("'{$subjectFilter}' bo'yicha mos fan topilmadi.");
                return 0;
            }
        }

        // Bir fan (subject+semester) uchun nechta yozuv borligini sanaymiz — dublikatlarni topish uchun.
        $dupKeys = [];
        foreach ($items as $it) {
            $k = ($it['_subject'] ?? $it['subject_name'] ?? '') . '|' . ($it['_semester'] ?? '');
            $dupKeys[$k] = ($dupKeys[$k] ?? 0) + 1;
        }

        $rows = [];
        $conflicts = 0;
        $dupSubjects = 0;
        foreach ($items as $it) {
            $grade = $it['grade'] ?? null;
            $point = $it['total_point'] ?? null;
            $fcs = (bool) ($it['finish_credit_status'] ?? false);
            $gradeNum = is_numeric($grade) ? round((float) $grade, 2) : null;
            $pointNum = is_numeric($point) ? (float) $point : null;

            // Aniq yiqilgan: baho 0 yoki 2 (isAcademicRecordDebt bilan mos; pass/fail fanda 1=o'tdi).
            $clearlyFailed = $gradeNum !== null && ($gradeNum === 0.0 || $gradeNum === 2.0);
            // Aniq o'tgan: baho >= 3 yoki ball >= 56.
            $clearlyPassed = ($gradeNum !== null && $gradeNum >= 3.0) || ($pointNum !== null && $pointNum >= 56.0);

            // Ziddiyat = finish_credit_status baho/ball bilan mos kelmasligi.
            $conflictNote = '';
            if ($fcs && $clearlyFailed) {
                $conflictNote = '⚠ Ha, lekin yiqilgan';
                $conflicts++;
            } elseif (!$fcs && $clearlyPassed) {
                $conflictNote = "⚠ Yo'q, lekin o'tgan";
                $conflicts++;
            }

            $k = ($it['_subject'] ?? $it['subject_name'] ?? '') . '|' . ($it['_semester'] ?? '');
            $isDup = ($dupKeys[$k] ?? 0) > 1;

            $notes = [];
            if ($isDup) {
                $notes[] = 'DUBLIKAT×' . $dupKeys[$k];
            }
            if ($conflictNote) {
                $notes[] = $conflictNote;
            }

            $rows[] = [
                mb_strimwidth((string) ($it['subject_name'] ?? ''), 0, 32, '…'),
                (string) ($it['semester_name'] ?? ($it['_semester'] ?? '')),
                (string) ($it['credit'] ?? ''),
                (string) ($it['total_point'] ?? ''),      // Ball
                $grade === null ? '—' : (string) $grade,   // Baho
                $fcs ? 'Ha' : "Yo'q",                       // O'zlashtirgan
                ($it['retraining_status'] ?? false) ? 'Ha' : '·',
                implode(' ', $notes),
            ];
        }
        $dupSubjects = count(array_filter($dupKeys, fn($n) => $n > 1));

        $this->table(
            ['Fan', 'Semestr', 'Kredit', 'Ball', 'Baho', "O'zlash.\n(finish_credit)", 'Qayta', 'Izoh'],
            $rows
        );

        $this->newLine();
        if ($conflicts > 0) {
            $this->error("⚠ {$conflicts} ta yozuvda finish_credit_status baho/ball bilan mos emas ('Ha, lekin yiqilgan' yoki 'Yo\\'q, lekin o\\'tgan').");
            $this->line('  → Web va Excel bu maydonlarni har xil talqin qilib, farqli ko\'rsatishi mumkin.');
        }
        if ($dupSubjects > 0) {
            $this->warn("⚠ {$dupSubjects} ta fanda bir nechta yozuv (dublikat) bor.");
            $this->line('  → web va Excel har xil yozuvni tanlab qolgan bo\'lishi mumkin (masalan biri finish_credit=Ha,');
            $this->line('    ikkinchisi Yo\'q). Bu web "Yo\'q/baho 0" vs Excel "Ha/baho 2" farqini tushuntiradi.');
        }
        if ($conflicts === 0 && $dupSubjects === 0) {
            $this->info('Ziddiyat ham, dublikat ham topilmadi — API izchil qaytaryapti.');
        }

        if ($this->option('raw')) {
            $this->newLine();
            $this->info('=== XOM JSON ===');
            $this->line(json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return 0;
    }

    /**
     * HEMIS API'ga GET so'rovi. Xatolikda ekranga yozadi va null qaytaradi.
     */
    private function apiGet(string $url, string $token, array $query): ?array
    {
        try {
            $response = Http::connectTimeout(30)->timeout(60)->withoutVerifying()
                ->withToken($token)->get($url, $query);
        } catch (\Throwable $e) {
            $this->error('API so\'rovda xatolik: ' . $e->getMessage());
            return null;
        }

        if (!$response->successful()) {
            $this->error("API xato — HTTP {$response->status()} ({$url})");
            $this->line(substr($response->body(), 0, 400));
            return null;
        }

        return $response->json();
    }

    private function countLabel(int $n): string
    {
        return (string) $n;
    }
}
