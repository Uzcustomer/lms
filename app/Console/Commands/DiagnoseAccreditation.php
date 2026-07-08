<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Akkreditatsiya "O'zlashtirgan" ustunidagi nomutanosiblikni diagnostika qilish.
 *
 * HEMIS academic-record-list API xom javobini bitta talaba uchun tortib,
 * har bir fan bo'yicha `finish_credit_status` (O'zlashtirgan), `grade` (Baho)
 * va `total_point` (Ball) qiymatlarini ko'rsatadi. Web sahifa "Yo'q", Excel esa
 * "Ha" ko'rsatgan holatda API'ning haqiqatda nima qaytarayotganini aniqlaydi.
 */
class DiagnoseAccreditation extends Command
{
    protected $signature = 'hemis:diagnose-accreditation
        {student : Talabaning hemis_id yoki student_id_number}
        {--subject= : Fan nomi bo\'yicha filter (qism matn, masalan "Tibbiyot kasbiga kirish")}
        {--raw : Mos yozuvlarning xom JSON javobini chiqarish}';

    protected $description = 'Talaba akkreditatsiyasidagi O\'zlashtirgan (finish_credit_status) nomutanosibligini API orqali tekshirish';

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

        // Talabani hemis_id yoki student_id_number bo'yicha topamiz.
        $student = Student::where('hemis_id', $ident)
            ->orWhere('student_id_number', $ident)
            ->first();

        if ($student) {
            $hemisId = $student->hemis_id;
            $this->info("Talaba: {$student->full_name} — hemis_id={$hemisId}, ID raqam={$student->student_id_number}");
        } else {
            // Bazada topilmasa, kiritilgan qiymatni to'g'ridan-to'g'ri hemis_id deb olamiz.
            $hemisId = $ident;
            $this->warn("Bazada talaba topilmadi — '{$ident}' hemis_id sifatida ishlatiladi.");
        }
        $this->newLine();

        // API'dan academic-record-list ni _student bo'yicha tortamiz.
        $items = [];
        $page = 1;
        do {
            try {
                $response = Http::connectTimeout(30)
                    ->timeout(60)
                    ->withoutVerifying()
                    ->withToken($token)
                    ->get("{$baseUrl}/v1/data/academic-record-list", [
                        '_student' => $hemisId,
                        'page' => $page,
                        'limit' => 200,
                    ]);
            } catch (\Throwable $e) {
                $this->error('API so\'rovda xatolik: ' . $e->getMessage());
                return 1;
            }

            if (!$response->successful()) {
                $this->error("API xato — HTTP {$response->status()}");
                $this->line(substr($response->body(), 0, 500));
                return 1;
            }

            $json = $response->json();
            $data = $json['data'] ?? [];
            $pageItems = $data['items'] ?? [];
            // Ehtiyot chorasi: server _student filtrini e'tiborsiz qoldirsa,
            // mijoz tomonida ham filterlaymiz.
            foreach ($pageItems as $it) {
                if ((string) ($it['_student'] ?? '') === (string) $hemisId) {
                    $items[] = $it;
                }
            }
            $pagination = $data['pagination'] ?? [];
            $pageCount = (int) ($pagination['pageCount'] ?? 1);
            $page++;
        } while ($page <= $pageCount);

        if (empty($items)) {
            $this->warn('Bu talaba uchun academic-record-list bo\'sh qaytdi.');
            return 0;
        }

        if ($subjectFilter) {
            $items = array_values(array_filter($items, fn($it) => mb_stripos((string) ($it['subject_name'] ?? ''), $subjectFilter) !== false));
            if (empty($items)) {
                $this->warn("'{$subjectFilter}' bo'yicha mos fan topilmadi.");
                return 0;
            }
        }

        // Jadval: har bir fan bo'yicha xom qiymatlar.
        $rows = [];
        $conflicts = 0;
        foreach ($items as $it) {
            $grade = $it['grade'] ?? null;
            $fcs = (bool) ($it['finish_credit_status'] ?? false);
            $gradeNumeric = is_numeric($grade) ? (float) $grade : null;

            // Ziddiyat: kredit olingan (Ha) deb belgilangan, lekin baho yiqilgan (< 3).
            $isConflict = $fcs && $gradeNumeric !== null && $gradeNumeric < 3.0;
            if ($isConflict) {
                $conflicts++;
            }

            $rows[] = [
                mb_strimwidth((string) ($it['subject_name'] ?? ''), 0, 34, '…'),
                (string) ($it['semester_name'] ?? ''),
                (string) ($it['credit'] ?? ''),
                (string) ($it['total_point'] ?? ''),      // Ball
                $grade === null ? '—' : (string) $grade,   // Baho
                $fcs ? 'Ha' : "Yo'q",                       // O'zlashtirgan
                ($it['retraining_status'] ?? false) ? 'Ha' : "Yo'q",
                $isConflict ? '⚠ ZIDDIYAT' : '',
            ];
        }

        $this->table(
            ['Fan', 'Semestr', 'Kredit', 'Ball', 'Baho', "O'zlashtirgan\n(finish_credit)", 'Qayta o\'qish', 'Izoh'],
            $rows
        );

        $this->newLine();
        if ($conflicts > 0) {
            $this->error("⚠ {$conflicts} ta ziddiyatli yozuv: finish_credit_status=true (O'zlashtirgan=Ha), lekin baho < 3 (yiqilgan).");
            $this->line('  → Excel bu yozuvni "Ha" ko\'rsatadi (finish_credit_status ni to\'g\'ridan-to\'g\'ri oladi),');
            $this->line('  → web sahifa esa bahoni qayta hisoblab "Yo\'q" ko\'rsatadi. Manba maydonlarining o\'zi zid.');
        } else {
            $this->info('Ziddiyatli yozuv topilmadi (finish_credit_status baho bilan mos).');
        }

        if ($this->option('raw')) {
            $this->newLine();
            $this->info('=== XOM JSON ===');
            $this->line(json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return 0;
    }
}
