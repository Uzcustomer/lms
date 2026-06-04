<?php

namespace App\Services;

use App\Models\VedomostSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Yuklangan vedomostni (skaner PDF + menejer Excel) tizimning YN qaydnoma
 * ma'lumoti bilan Claude API orqali solishtiradi va nomuvofiqliklarni qaytaradi.
 *
 * Faqat tavsiya — yakuniy qarorni registrator ofisi xodimi qiladi.
 */
class VedomostAiChecker
{
    public function __construct(
        private YnQaydnomaDataService $qaydnoma,
        private VedomostMergeService $merge
    ) {
    }

    public static function isConfigured(): bool
    {
        return !empty(config('services.anthropic.api_key'));
    }

    /**
     * @return array{verdict:string, summary:string, discrepancies:array, signatures:array}
     */
    public function check(VedomostSubmission $v): array
    {
        if (!self::isConfigured()) {
            throw new RuntimeException('ANTHROPIC_API_KEY sozlanmagan.');
        }
        if (!$v->pdf_path || !Storage::disk('public')->exists($v->pdf_path)) {
            throw new RuntimeException('Skaner PDF topilmadi.');
        }

        // Birlashtirilgan vedomost: bitta o'zak guruh × o'zak fan uchun bitta varaq,
        // unda barcha guruhcha/variant talabalari jamlangan. Shuning uchun kutilgan
        // ma'lumotni barcha siblinglardan birlashtirib quramiz.
        $expected = $this->buildMergedExpected($v);
        if (!$expected) {
            throw new RuntimeException('Tizimda bu guruh/fan uchun YN qaydnoma ma\'lumoti topilmadi.');
        }

        $pdfBase64 = base64_encode(Storage::disk('public')->get($v->pdf_path));

        $content = [
            [
                'type' => 'text',
                'text' => "TIZIMDAGI (kutilgan) YN QAYDNOMA MA'LUMOTI (JSON):\n"
                    . json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
            [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => $pdfBase64,
                ],
            ],
            [
                'type' => 'text',
                'text' => "Yuqoridagi SKANER (PDF) ni tizim ma'lumoti bilan solishtiring va sxema bo'yicha natija qaytaring.",
            ],
        ];

        $payload = [
            'model' => config('services.anthropic.model'),
            // Streaming ishlatamiz, shuning uchun katta max_tokens xavfsiz (timeout yo'q).
            // Thinking ham max_tokens ichiga kiradi — katta guruhda javob kesilmasligi
            // uchun keng chegara. Bu shift — ceiling, model faqat kerakligini sarflaydi.
            'max_tokens' => 48000,
            'thinking' => ['type' => 'adaptive'],
            'system' => [[
                'type' => 'text',
                'text' => $this->systemPrompt(),
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'output_config' => [
                // effort: medium — kamroq o'ylash => kamroq token va tezroq javob.
                'effort' => 'medium',
                'format' => ['type' => 'json_schema', 'schema' => $this->schema()],
            ],
            'messages' => [[
                'role' => 'user',
                'content' => $content,
            ]],
        ];

        // Streaming: javob bo'lak-bo'lak keladi — ulanish "0 bayt"da qotmaydi va
        // uzoq generatsiyada ham timeout bo'lmaydi (cURL 28 muammosining yechimi).
        $result = $this->streamMessages($payload);

        $stopReason = $result['stop_reason'];
        if ($stopReason === 'refusal') {
            throw new RuntimeException('Claude so\'rovni rad etdi (refusal).');
        }
        if ($stopReason === 'max_tokens') {
            throw new RuntimeException('Claude javobi max_tokens chegarasida kesildi — natija to\'liq emas.');
        }

        // Structured output: matnli bo'laklar sxemaga mos JSON'ni tashkil etadi.
        $jsonText = trim($result['text']);
        if ($jsonText === '') {
            throw new RuntimeException('Claude javobida natija topilmadi.');
        }

        $parsed = json_decode($jsonText, true);
        if (!is_array($parsed)) {
            throw new RuntimeException('Claude javobini JSON sifatida o\'qib bo\'lmadi.');
        }

        return [
            'verdict' => $parsed['verdict'] ?? 'issues',
            'summary' => $parsed['summary'] ?? '',
            'discrepancies' => $parsed['discrepancies'] ?? [],
            'signatures' => $parsed['signatures'] ?? [],
        ];
    }

    /**
     * Claude Messages API'ga STREAM (SSE) so'rov yuboradi va matn bo'laklarini
     * hamda stop_reason'ni yig'ib qaytaradi. Streaming tufayli ulanish bo'sh
     * turmaydi — uzoq javoblarda ham cURL timeout (xato 28) bo'lmaydi.
     *
     * @return array{text: string, stop_reason: ?string}
     */
    private function streamMessages(array $payload): array
    {
        $payload['stream'] = true;

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => config('services.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ])
            ->withOptions(['stream' => true])
            ->timeout((int) config('services.anthropic.timeout', 280))
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Claude API xatosi: ' . $response->status() . ' ' . $response->body());
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $text = '';
        $stopReason = null;

        while (!$stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;

            // SSE qatorma-qator: "data: {...}" bo'laklarini ajratamiz.
            while (($nl = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $nl));
                $buffer = substr($buffer, $nl + 1);

                if ($line === '' || !str_starts_with($line, 'data:')) {
                    continue;
                }
                $json = trim(substr($line, 5));
                if ($json === '' || $json === '[DONE]') {
                    continue;
                }
                $event = json_decode($json, true);
                if (!is_array($event)) {
                    continue;
                }

                switch ($event['type'] ?? '') {
                    case 'content_block_delta':
                        // Faqat matn deltalari (thinking_delta'ni e'tiborsiz qoldiramiz).
                        if (($event['delta']['type'] ?? '') === 'text_delta') {
                            $text .= $event['delta']['text'] ?? '';
                        }
                        break;
                    case 'message_delta':
                        $stopReason = $event['delta']['stop_reason'] ?? $stopReason;
                        break;
                    case 'error':
                        throw new RuntimeException('Claude API stream xatosi: ' . ($event['error']['message'] ?? 'noma\'lum'));
                }
            }
        }

        return ['text' => $text, 'stop_reason' => $stopReason];
    }

    /**
     * Birlashtirilgan vedomost uchun kutilgan ma'lumot — o'zak guruh × o'zak fanga
     * tegishli barcha guruhcha/variant yozuvlarining ma'lumotini bitta tuzilmaga
     * jamlaydi (talabalar birlashtiriladi, o'qituvchilar/sanalar to'planadi).
     */
    private function buildMergedExpected(VedomostSubmission $v): ?array
    {
        $siblings = $this->merge->siblingsOf($v);

        $parts = [];
        foreach ($siblings as $sib) {
            $data = $this->qaydnoma->buildExpectedData(
                (string) $sib->group_hemis_id,
                (string) $sib->subject_id,
                (string) $sib->semester_code
            );
            if ($data) {
                $parts[] = $data;
            }
        }

        if (empty($parts)) {
            return null;
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        $first = $parts[0];
        $groupNames = [];
        $maruzachi = [];
        $amaliyot = [];
        $ynSanasi = [];
        $students = [];
        $seen = [];

        foreach ($parts as $p) {
            if (!empty($p['guruh'])) {
                $groupNames[$p['guruh']] = true;
            }
            foreach (explode(', ', (string) ($p['maruzachi'] ?? '')) as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $maruzachi[$name] = true;
                }
            }
            foreach (explode(', ', (string) ($p['amaliyot_oqituvchilari'] ?? '')) as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $amaliyot[$name] = true;
                }
            }
            if (!empty($p['yn_sanasi'])) {
                $ynSanasi[$p['yn_sanasi']] = true;
            }
            foreach ($p['talabalar'] ?? [] as $row) {
                // Bir talaba ikki marta tushmasligi uchun talaba ID (yoki FISH) bo'yicha dedupe.
                $key = !empty($row['student_id']) ? 'id:' . $row['student_id'] : 'fish:' . ($row['fish'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $students[] = $row;
            }
        }

        // Talabalarni qayta raqamlaymiz (birlashgan ro'yxat bo'yicha 1..N).
        foreach ($students as $i => &$row) {
            $row['n'] = $i + 1;
        }
        unset($row);

        $subgroups = array_keys($groupNames);
        sort($subgroups, SORT_NATURAL | SORT_FLAG_CASE);
        $rootGroup = $this->merge->rootGroupName($v->group_name);

        return array_merge($first, [
            'fan' => $this->merge->rootSubjectName($first['fan'] ?? $v->subject_name),
            'guruh' => $rootGroup . (count($subgroups) > 1 ? ' (' . implode(', ', $subgroups) . ')' : ''),
            'maruzachi' => empty($maruzachi) ? null : implode(', ', array_keys($maruzachi)),
            'amaliyot_oqituvchilari' => implode(', ', array_keys($amaliyot)),
            'yn_sanasi' => empty($ynSanasi) ? null : implode(', ', array_keys($ynSanasi)),
            'jami_talabalar' => count($students),
            'talabalar' => $students,
        ]);
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
Siz universitet o'quv bo'limining vedomost (YN qaydnoma) tekshiruvchi yordamchisisiz.
Vazifa: o'qituvchi taqdim etgan skaner qilingan vedomostni (PDF) tizimdagi rasmiy
YN qaydnoma ma'lumoti bilan solishtirib, nomuvofiqliklarni aniqlash.

MUHIM: Bitta vedomost bitta O'ZAK guruh × o'zak fan uchun bir varaqda olinadi va
bir nechta guruhcha (a/b/c, til oqimlari) talabalarini jamlashi mumkin. Tizim
ma'lumotidagi "guruh" maydonida o'zak guruh va uning guruhchalari ko'rsatilgan,
"talabalar" ro'yxati esa barcha guruhchalar talabalarining birlashmasi. Skanerda
shu barcha talabalar bo'lishi normal — buni xato deb belgilamang. O'qituvchilar
ham bir nechta bo'lishi mumkin (har guruhchaning o'qituvchisi).

Quyidagilarni tekshiring:
1) Sarlavha maydonlari tizim ma'lumotiga mos kelishi: fakultet nomi, o'quv yili, yo'nalish,
   fan nomi, ma'ruzachi, amaliyot o'qituvchilari, umumiy soat, kredit, YN o'tkazilgan sana,
   kurs, semestr, guruh nomi.
2) Talabalar ro'yxati: tizimdagi har bir talaba mavjudmi, FISH va talaba ID raqami to'g'ri
   yozilganmi, ortiqcha yoki kam talaba bormi.
3) Har bir talaba uchun JN, MT, ON (JB+MT+ON), OSKE, Test ustunlaridagi qiymatlar tizim
   ma'lumotiga mos kelishi.
4) Imzolar — skanerda HAQIQIY qo'l qo'yilganmi (fan o'qituvchisi, fakultet dekani,
   kafedra mudiri). DIQQAT: blankada "imzo", "M.O'." (muhr o'rni), "F.I.Sh", "___"
   kabi OLDINDAN CHOP ETILGAN yorliq va chiziqlar bo'ladi — bular imzo EMAS, faqat
   joy belgisi. Imzo MAVJUD deb faqat chiziq ustida/yonida haqiqiy qo'lda chizilgan
   belgi (parah, ruchka izi) yoki muhr bo'lsa hisoblang. Agar katakda faqat "imzo"
   so'zi, F.I.Sh yoki bo'sh chiziq bo'lsa — imzo YO'Q (false). Ishonchsiz bo'lsangiz
   ham YO'Q deb belgilang (false). Har bir yetishmayotgan imzoni discrepancies'ga ham
   qo'shing (severity=high), masalan field="Imzo: fakultet dekani".
5) Ichki izchillik: o'zlashtirish ko'rsatkichi (%) ga mos ECTS harfi va baho to'g'ri qo'yilganmi
   (90-100=A/a'lo, 85-89=B+, 70-84=B/yaxshi, 60-69=C/o'rta, 0-59=F/qoniqarsiz). Jami talabalar
   soni, a'lo/yaxshi/o'rta/qoniqarsiz/kelmadi/qo'yilmadi sonlari hamda guruh o'zlashtirish va
   sifat ko'rsatkichlari ustundagi qiymatlarga mos kelishini tekshiring.

ISM YOZILISHI (juda muhim — noto'g'ri belgilamang): Vedomostda ism QISQARTIRILGAN
yozilishi MAQBUL — familiya to'liq, ism va otasining ismi bosh harf bilan
("Familiya I.S." yoki "I.S. Familiya", masalan tizimdagi "AZIZI AHMAD RESHAD" →
skanerda "Azizi A.R" yoki "A.R. Azizi"). Bu, ayniqsa, xodimlarga (ma'ruzachi,
amaliyot o'qituvchilari, fan mas'uli, kafedra mudiri, imzo egalari) tegishli.
Familiya mos kelsa va bosh harflar tizimdagi to'liq ismga mos bo'lsa — buni
nomuvofiqlik DEB BELGILAMANG. Faqat familiya boshqa bo'lsa yoki bosh harflar
to'liq ismga mos kelmasa belgilang. (Talabani esa talaba ID raqami orqali ham
tasdiqlash mumkin.)

Qoidalar:
- Faqat sxema bo'yicha JSON qaytaring, qo'shimcha matn yozmang.
- verdict: nomuvofiqlik topilmasa "ok", aks holda "issues".
- Har bir nomuvofiqlik uchun: field (qaysi maydon/talaba), severity (high/medium/low),
  expected (tizimdagi qiymat), found (skanerdagi qiymat), note (qisqa izoh, o'zbekcha).
- severity: baho/talaba/imzo xatolari = high; sarlavha xatolari = medium; kichik nomuvofiqlik = low.
- Agar skaner sifati past bo'lib biror qiymat o'qilmasa, found="o'qib bo'lmadi" deb belgilang (severity=low).
TXT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'verdict' => ['type' => 'string', 'enum' => ['ok', 'issues']],
                'summary' => ['type' => 'string'],
                'discrepancies' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'severity' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                            'expected' => ['type' => 'string'],
                            'found' => ['type' => 'string'],
                            'note' => ['type' => 'string'],
                        ],
                        'required' => ['field', 'severity', 'expected', 'found', 'note'],
                    ],
                ],
                'signatures' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'oqituvchi' => ['type' => 'boolean'],
                        'dekan' => ['type' => 'boolean'],
                        'kafedra_mudiri' => ['type' => 'boolean'],
                    ],
                    'required' => ['oqituvchi', 'dekan', 'kafedra_mudiri'],
                ],
            ],
            'required' => ['verdict', 'summary', 'discrepancies', 'signatures'],
        ];
    }
}
