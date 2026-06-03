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
            // Katta guruhda (ko'p talaba) ko'p nomuvofiqlik + adaptive thinking bo'lishi
            // mumkin — javob yarmida kesilmasligi uchun yetarli headroom.
            'max_tokens' => 16000,
            'thinking' => ['type' => 'adaptive'],
            'system' => [[
                'type' => 'text',
                'text' => $this->systemPrompt(),
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'output_config' => [
                'format' => ['type' => 'json_schema', 'schema' => $this->schema()],
            ],
            'messages' => [[
                'role' => 'user',
                'content' => $content,
            ]],
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => config('services.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('services.anthropic.timeout', 180))
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Claude API xatosi: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        $stopReason = $data['stop_reason'] ?? null;
        if ($stopReason === 'refusal') {
            throw new RuntimeException('Claude so\'rovni rad etdi (refusal).');
        }
        if ($stopReason === 'max_tokens') {
            throw new RuntimeException('Claude javobi max_tokens chegarasida kesildi — natija to\'liq emas.');
        }

        // Structured output: matn blokida sxemaga mos JSON bo'ladi
        $jsonText = null;
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text' && !empty($block['text'])) {
                $jsonText = $block['text'];
                break;
            }
        }
        if ($jsonText === null) {
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
4) Imzolar mavjudligi (skanerda ko'rinishi): fan o'qituvchisi imzosi, fakultet dekani imzosi,
   kafedra mudiri imzosi.
5) Ichki izchillik: o'zlashtirish ko'rsatkichi (%) ga mos ECTS harfi va baho to'g'ri qo'yilganmi
   (90-100=A/a'lo, 85-89=B+, 70-84=B/yaxshi, 60-69=C/o'rta, 0-59=F/qoniqarsiz). Jami talabalar
   soni, a'lo/yaxshi/o'rta/qoniqarsiz/kelmadi/qo'yilmadi sonlari hamda guruh o'zlashtirish va
   sifat ko'rsatkichlari ustundagi qiymatlarga mos kelishini tekshiring.

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
