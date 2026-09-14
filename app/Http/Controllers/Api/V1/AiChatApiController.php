<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-side proxy for the mobile "TDTU AI Yordamchi" (Gemini).
 *
 * The API key lives only in .env on the server. The app sends the
 * conversation (history + new message + optional attachments + the
 * student's data context) and receives Gemini's SSE stream relayed
 * verbatim — one `data: {json}` line per chunk — so the UI keeps its
 * token-by-token rendering.
 */
class AiChatApiController extends Controller
{
    private const MAX_HISTORY_TURNS = 40;

    public function chat(Request $request): JsonResponse|StreamedResponse
    {
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            return response()->json([
                'message' => "AI yordamchi hozircha o'chirilgan.",
            ], 503);
        }

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:8000'],
            // Markdown dump of the student's grades/schedule built by the app.
            'context' => ['nullable', 'string', 'max:400000'],
            // JSON array of {role: "user"|"model", text: string}.
            'history' => ['nullable', 'string', 'max:400000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

        $contents = $this->historyContents($data['history'] ?? null);

        $parts = [];
        foreach ($request->file('attachments', []) as $file) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'data' => base64_encode((string) file_get_contents($file->getRealPath())),
                ],
            ];
        }
        $message = trim((string) ($data['message'] ?? ''));
        if ($message !== '') {
            $parts[] = ['text' => $message];
        }
        if ($parts === []) {
            return response()->json(['message' => 'Xabar bo\'sh.'], 422);
        }
        $contents[] = ['role' => 'user', 'parts' => $parts];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $this->systemPrompt($data['context'] ?? null)]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.95,
                'topK' => 40,
                'maxOutputTokens' => 2048,
            ],
        ];

        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse";

        try {
            $upstream = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->withOptions(['stream' => true])
                ->timeout((int) config('services.gemini.timeout', 120))
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('Gemini proxy request failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'AI xizmatiga ulanib bo\'lmadi. Keyinroq urinib ko\'ring.',
            ], 502);
        }

        if (!$upstream->successful()) {
            $status = $upstream->status();
            Log::warning('Gemini proxy upstream error', [
                'status' => $status,
                'body' => mb_substr((string) $upstream->body(), 0, 500),
            ]);
            return response()->json(['message' => $this->friendlyError($status)], 502);
        }

        $body = $upstream->toPsrResponse()->getBody();

        return response()->stream(function () use ($body) {
            while (!$body->eof()) {
                echo $body->read(8192);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function historyContents(?string $json): array
    {
        $turns = json_decode($json ?? '[]', true);
        if (!is_array($turns)) {
            return [];
        }

        $contents = [];
        foreach (array_slice($turns, -self::MAX_HISTORY_TURNS) as $turn) {
            if (!is_array($turn)) {
                continue;
            }
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $contents[] = [
                'role' => ($turn['role'] ?? '') === 'model' ? 'model' : 'user',
                'parts' => [['text' => $text]],
            ];
        }

        return $contents;
    }

    private function systemPrompt(?string $studentContext): string
    {
        $now = now()->timezone(config('app.timezone', 'Asia/Tashkent'));
        $months = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun',
            'iyul', 'avgust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'];
        $weekdays = ['dushanba', 'seshanba', 'chorshanba', 'payshanba',
            'juma', 'shanba', 'yakshanba'];
        $today = sprintf(
            '%d-yil %d-%s (%s), %s',
            $now->year,
            $now->day,
            $months[$now->month - 1],
            $weekdays[$now->dayOfWeekIso - 1],
            $now->format('H:i')
        );
        $isoToday = $now->format('Y-m-d');

        $base = "Sen TDTU (Toshkent Davlat Tibbiyot Universiteti) talabasining "
            . "shaxsiy AI yordamchisisan. Ismingiz \"TDTU AI Yordamchi\". "
            . "Talaba sening egang — uning ma'lumotlari sen uchun ochiq va shaxsiy "
            . "emas. Sen uning baholari, davomati, jadvali va boshqa o'quv "
            . "ma'lumotlarini tahlil qilib, savollariga javob berishing kerak.\n\n"
            . "⚠️ BUGUNGI SANA: {$today}\n"
            . "ISO format: {$isoToday}\n"
            . "Bu sanani DOIMO eslab qol. O'tib ketgan sanalar haqida \"yaqin keladigan\" "
            . "yoki \"hozirda muhim\" deb gapirma. Faqat {$isoToday} dan KEYINGI sanalar "
            . "kelajakda hisoblanadi. Ma'lumotlardagi har bir sanani bugungi sana "
            . "bilan solishtir va to'g'ri xulosa qil.\n\n"
            . "Qoidalar:\n"
            . "- O'zbek tilida javob ber, foydalanuvchi boshqa tilda yozsa o'sha tilda javob ber\n"
            . "- Aniq, qisqa, foydali javoblar ber\n"
            . "- Ma'lumotni tahlil qilganda raqamlar va statistika bilan ko'rsat\n"
            . "- Tibbiyot, anatomiya, fiziologiya, farmakologiya bo'yicha ham yordam ber\n"
            . "- Foydalanuvchi rasm, PDF, audio yoki video yuborsa, uni diqqat bilan tahlil qil\n"
            . "- Agar ma'lumot yetarli bo'lmasa, qaysi sahifaga borish kerakligini tushuntir\n"
            . "- HECH QACHON \"Men shaxsiy ma'lumotlarga ega emasman\" deb javob berma — "
            . "barcha ma'lumotlar QUYIDA berilgan. Har bir fan nomi, bahosi, davomati bor\n"
            . "- Talaba baholarini so'rasa, quyidagi \"FANLAR VA BAHOLAR\" bo'limidagi har bir "
            . "fanni JN, MT, ON, OSKI, TEST, YN ballari bilan batafsil ko'rsat\n"
            . "- Imtihon/dars/muddat haqida gapirsang [O'TGAN] yoki [KELGUSI] yorlig'iga "
            . "qarab tahlil qil. O'tgan voqealarni tavsiya qilma\n"
            . "- Baholarni tahlil qilganda eng past va eng yuqori baholarni aniqlash, "
            . "diqqat qilish kerak bo'lgan fanlarni tavsiya qilish, GPA ni hisoblash "
            . "va umumiy tahlil ber\n";

        $studentContext = trim((string) $studentContext);
        if ($studentContext === '') {
            return $base . "\n\nTalaba ma'lumotlari hali yuklanmagan.";
        }

        return $base . "\n\n=== TALABA MA'LUMOTLARI ===\n{$studentContext}\n=== MA'LUMOTLAR TUGADI ===";
    }

    private function friendlyError(int $status): string
    {
        return match (true) {
            $status === 429 => "AI limit tugadi. Biroz kutib qayta urinib ko'ring.",
            $status === 401, $status === 403 => "AI kaliti noto'g'ri yoki faol emas.",
            $status === 413 => 'Fayl hajmi juda katta. 20MB dan kichikroq fayl yuklang.',
            default => 'AI xizmatida xatolik. Keyinroq urinib ko\'ring.',
        };
    }
}
