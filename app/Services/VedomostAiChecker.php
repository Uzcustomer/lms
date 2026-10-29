<?php

namespace App\Services;

use App\Models\Student;
use App\Models\VedomostSubmission;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Yuklangan vedomostni (skaner PDF + menejer Excel) tizimning YN qaydnoma
 * ma'lumoti bilan Gemini API orqali solishtiradi va nomuvofiqliklarni qaytaradi.
 *
 * Faqat tavsiya — yakuniy qarorni registrator ofisi xodimi qiladi.
 */
class VedomostAiChecker
{
    public function __construct(
        private YnQaydnomaDataService $qaydnoma,
        private VedomostMergeService $merge,
        private VedomostGradeCalculator $calc,
        private VedomostGeminiClient $gemini,
        private VedomostAiSettings $settings
    ) {
    }

    public static function isConfigured(): bool
    {
        return VedomostGeminiClient::isConfigured();
    }

    /**
     * @return array{verdict:string, summary:string, discrepancies:array, signatures:array}
     */
    public function check(VedomostSubmission $v): array
    {
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

        // 1-bosqich: AI faqat SKANERNI O'QIYDI (transkripsiya) — hisoblamaydi,
        // solishtirmaydi.
        $scan = $this->transcribeScan($v, $expected);

        // 2-bosqich: PHP hisoblaydi va solishtiradi (determinik, aniq).
        $result = $this->compare($expected, $scan);
        $result['model'] = $scan['_model'] ?? null;

        return $result;
    }

    /**
     * Gemini orqali skanerni strukturali JSONga o'qiydi (og'irliklar, talabalar
     * yozilgan qiymatlari, imzo/muhr, sarlavha nomuvofiqliklari). Hisob yo'q.
     */
    private function transcribeScan(VedomostSubmission $v, array $expected): array
    {
        $parts = [
            ['text' => "TIZIMDAGI (kutilgan) YN QAYDNOMA MA'LUMOTI (JSON) — faqat sarlavha\n"
                . "nomuvofiqliklarini baholash uchun:\n"
                . json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)],
            ['inlineData' => [
                'mimeType' => 'application/pdf',
                'data' => base64_encode(Storage::disk('public')->get($v->pdf_path)),
            ]],
            ['text' => "SKANER (PDF) dan sxema bo'yicha qiymatlarni O'QIB chiqing. Sonlarni"
                . " HISOBLAMANG va solishtirmang — faqat yozilganini ko'chiring."],
        ];

        $res = $this->gemini->generateJson($parts, $this->transcribeSchema(), $this->transcribePrompt(), $v->id);
        $res['data']['_model'] = $res['model'];

        return $res['data'];
    }

    /**
     * Birlashtirilgan vedomost uchun kutilgan ma'lumot — o'zak guruh × o'zak fanga
     * tegishli barcha guruhcha/variant yozuvlarining ma'lumotini bitta tuzilmaga
     * jamlaydi (talabalar birlashtiriladi, o'qituvchilar/sanalar to'planadi).
     */
    private function buildMergedExpected(VedomostSubmission $v): ?array
    {
        // Guruhcha tartibida (a, b, c) — amaliyot o'qituvchilari shu tartibda kelishi uchun.
        $siblings = $this->merge->siblingsOf($v)
            ->sortBy('group_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

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
        $amaliyotList = [];
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
            // Amaliyot: har guruhchadan BITTA o'qituvchi, guruhcha tartibida (a, b, c).
            // Takror (bir o'qituvchi bir nechta guruhchada) bo'lsa bir marta ko'rsatamiz.
            $amaliyot = trim((string) ($p['amaliyot_oqituvchilari'] ?? ''));
            if ($amaliyot !== '' && !in_array($amaliyot, $amaliyotList, true)) {
                $amaliyotList[] = $amaliyot;
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
            'amaliyot_oqituvchilari' => implode(', ', $amaliyotList),
            'yn_sanasi' => empty($ynSanasi) ? null : implode(', ', array_keys($ynSanasi)),
            'jami_talabalar' => count($students),
            'talabalar' => $students,
        ]);
    }

    private function transcribePrompt(): string
    {
        return <<<'TXT'
Siz vedomost (YN qaydnoma) skanerini O'QIYDIGAN yordamchisiz. Vazifa — SKANER (PDF)dan
qiymatlarni strukturali ko'chirib olish. SONLARNI HISOBLAMANG, ballarni O'ZINGIZ
chiqarmang, tizim bilan SOLISHTIRMANG — faqat skanerda YOZILGANINI o'qing (sarlavhadan
tashqari; sonlarni PHP hisoblaydi).

1) OG'IRLIKLAR (weights): sarlavhaning "ball" qatorida har nazorat turi (JB, MT, ON,
   OSKE, Test) ostidagi maksimal ball. Butun son sifatida oling; tegishli ustun yo'q
   bo'lsa 0.

2) TALABALAR (students): jadvalning har bir TO'LDIRILGAN qatoridan o'qing:
   - student_id — "Talaba ID" ustuni (raqam);
   - fish — talaba F.I.Sh;
   - jb_pct, mt_pct, on_pct, oski_pct, test_pct — har ustunning "%" qismi;
   - jb_ball, mt_ball, on_ball, oski_ball, test_ball — har ustunning "ball" qismi;
   - jbmton_ball — "JB+MT+ON" ustunidagi ball;
   - ozlashtirish — "O'zlashtirish ko'rsatkichi" ustuni;
   - ects — ECTS ustuni; baho — yakuniy baho ustuni.
   Bo'sh katakni "" qoldiring. Sonlarni nuqta bilan yozing (masalan 19.2). Bo'sh
   (talaba yo'q) qatorlarni o'qimang.

3) IMZOLAR (signatures) — skanerda HAQIQIY qo'l qo'yilganmi:
   - oqituvchi, dekan, kafedra_mudiri — chiziq ustida haqiqiy qo'lda chizilgan imzo (parah,
     ruchka izi) bo'lsa true;
   - muhr — "M.O'" yonida rasmiy DUMALOQ muhr/shtamp bo'lsa true.
   DIQQAT: "imzo", "M.O'", "F.I.Sh", "___" — bular CHOP ETILGAN yorliq, imzo EMAS → false.
   Ishonchsiz bo'lsangiz false.

4) HEADER_ISSUES — faqat SARLAVHA maydonlarini (fakultet, o'quv yili, yo'nalish, fan nomi,
   ma'ruzachi, amaliyot o'qituvchilari, umumiy soat, kredit, YN sanasi, kurs, semestr,
   guruh) tizim ma'lumoti bilan solishtiring; FARQ bo'lsa shu yerga yozing
   (field, severity, expected=tizim, found=skaner, note — o'zbekcha). Ism qisqartmasi
   (familiya to'liq + ism/sharif bosh harf, masalan "Azizi A.R") MAQBUL — familiya va
   bosh harflar mos bo'lsa belgilamang. Talaba ballari/sonlarini bu yerga YOZMANG
   (ularni PHP solishtiradi).

Faqat sxema bo'yicha JSON qaytaring, boshqa matn yozmang.
TXT;
    }

    /**
     * Gemini responseSchema (OpenAPI subset: turlar katta harfda, additionalProperties yo'q).
     */
    private function transcribeSchema(): array
    {
        $str = ['type' => 'STRING'];
        $int = ['type' => 'INTEGER'];
        $bool = ['type' => 'BOOLEAN'];
        $obj = fn (array $props) => [
            'type' => 'OBJECT',
            'properties' => $props,
            'required' => array_keys($props),
            'propertyOrdering' => array_keys($props),
        ];

        $studentFields = [
            'student_id', 'fish',
            'jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'on_pct', 'on_ball',
            'jbmton_ball', 'oski_pct', 'oski_ball', 'test_pct', 'test_ball',
            'ozlashtirish', 'ects', 'baho',
        ];

        $issue = $obj([
            'field' => $str,
            'severity' => ['type' => 'STRING', 'enum' => ['high', 'medium', 'low']],
            'expected' => $str,
            'found' => $str,
            'note' => $str,
        ]);

        return $obj([
            'weights' => $obj(['jb' => $int, 'mt' => $int, 'on' => $int, 'oski' => $int, 'test' => $int]),
            'students' => ['type' => 'ARRAY', 'items' => $obj(array_fill_keys($studentFields, $str))],
            'signatures' => $obj(['oqituvchi' => $bool, 'dekan' => $bool, 'kafedra_mudiri' => $bool, 'muhr' => $bool]),
            'header_issues' => ['type' => 'ARRAY', 'items' => $issue],
        ]);
    }

    /**
     * PHP tomonida: tizim foizlari + skaner og'irliklari bilan ball/o'zlashtirish/ECTS/
     * bahoni hisoblab, skanerdan o'qilgan qiymatlar bilan solishtiradi.
     *
     * @return array{verdict:string, summary:string, discrepancies:array, signatures:array}
     */
    private function compare(array $expected, array $scan): array
    {
        $disc = [];

        // 1) Sarlavha (matnli) nomuvofiqliklari — AI bahosi.
        foreach (($scan['header_issues'] ?? []) as $d) {
            if (is_array($d) && !empty($d['field'])) {
                $disc[] = $this->normIssue($d);
            }
        }

        // 2) Og'irliklar (skanerdan) va kurs.
        $w = [
            'jn'   => (int) ($scan['weights']['jb']   ?? 0),
            'mt'   => (int) ($scan['weights']['mt']   ?? 0),
            'on'   => (int) ($scan['weights']['on']   ?? 0),
            'oski' => (int) ($scan['weights']['oski'] ?? 0),
            'test' => (int) ($scan['weights']['test'] ?? 0),
        ];
        $levelCode = (string) ($expected['level_code'] ?? '');

        // Skaner talabalarini ID bo'yicha indekslash.
        $scanById = [];
        foreach (($scan['students'] ?? []) as $s) {
            $id = $this->digits($s['student_id'] ?? '');
            if ($id !== '') {
                $scanById[$id] = $s;
            }
        }

        // JN/MT o'tish chegarasi — AI sozlamalaridan (xalqaro fakultet uchun alohida).
        $intlIds = $this->internationalStudentIds($expected['talabalar'] ?? []);
        $pass = (int) $this->settings->get('jn_mt_pass');
        $passIntl = (int) $this->settings->get('jn_mt_pass_international');

        $matched = [];
        foreach (($expected['talabalar'] ?? []) as $stu) {
            $id = $this->digits($stu['student_id'] ?? '');
            $label = 'Talaba ' . ($stu['fish'] ?? $id);
            $s = $id !== '' ? ($scanById[$id] ?? null) : null;
            if (!$s) {
                $disc[] = ['field' => $label . ' (' . $id . ')', 'severity' => 'high',
                    'expected' => "ro'yxatda", 'found' => "skanerda yo'q",
                    'note' => 'Tizimdagi talaba skanerda topilmadi.'];
                continue;
            }
            $matched[$id] = true;

            // Foizlar: tizim haqiqati vs skaner.
            $this->cmpNum($disc, $label . ' — JB %',   $stu['jn']   ?? null, $s['jb_pct']   ?? null);
            $this->cmpNum($disc, $label . ' — MT %',   $stu['mt']   ?? null, $s['mt_pct']   ?? null);
            $this->cmpNum($disc, $label . ' — ON %',   $stu['on']   ?? null, $s['on_pct']   ?? null);
            $this->cmpNum($disc, $label . ' — OSKE %', $stu['oski'] ?? null, $s['oski_pct'] ?? null);
            $this->cmpNum($disc, $label . ' — Test %', $stu['test'] ?? null, $s['test_pct'] ?? null);

            // Ballar: PHP hisoblagan (tizim foizi × skaner og'irligi) vs skaner.
            $e = $this->calc->compute(
                $stu['jn'] ?? null, $stu['mt'] ?? null, $stu['on'] ?? null,
                $stu['oski'] ?? null, $stu['test'] ?? null, $levelCode, $w,
                0.0, isset($intlIds[$id]) ? $passIntl : $pass
            );
            $this->cmpNum($disc, $label . ' — JB ball', $e['jb_ball'], $s['jb_ball'] ?? null);
            $this->cmpNum($disc, $label . ' — MT ball', $e['mt_ball'], $s['mt_ball'] ?? null);
            if ($w['on'] > 0) {
                $this->cmpNum($disc, $label . ' — ON ball', $e['on_ball'], $s['on_ball'] ?? null);
            }
            if ($w['oski'] > 0) {
                $this->cmpNum($disc, $label . ' — OSKE ball', $e['oski_ball'], $s['oski_ball'] ?? null);
            }
            if ($w['test'] > 0) {
                $this->cmpNum($disc, $label . ' — Test ball', $e['test_ball'], $s['test_ball'] ?? null);
            }
            $this->cmpNum($disc, $label . ' — JB+MT+ON ball', $e['jbmton_ball'], $s['jbmton_ball'] ?? null);

            // O'zlashtirish/ECTS/baho — maxsus holat (davomat/kelmadi/qo'yilmadi) bo'lmasa.
            if (!$this->isSpecialBaho(mb_strtolower(trim((string) ($s['baho'] ?? ''))))) {
                $this->cmpNum($disc, $label . " — O'zlashtirish", $e['ozlashtirish'], $s['ozlashtirish'] ?? null, 0.5, 'low');
                $this->cmpStr($disc, $label . ' — ECTS', $e['ects'], $s['ects'] ?? null);
                $this->cmpStr($disc, $label . ' — Baho', $e['baho'], $s['baho'] ?? null);
            }
        }

        // Skanerda ortiqcha (tizimda yo'q) talabalar.
        foreach ($scanById as $id => $s) {
            if (!isset($matched[$id])) {
                $disc[] = ['field' => 'Talaba ' . ($s['fish'] ?? $id) . ' (' . $id . ')', 'severity' => 'high',
                    'expected' => "tizimda yo'q", 'found' => 'skanerda bor',
                    'note' => 'Skanerdagi talaba tizimda topilmadi.'];
            }
        }

        // Jami talabalar soni.
        $sysN = count($expected['talabalar'] ?? []);
        $scanN = count($scan['students'] ?? []);
        if ($sysN !== $scanN) {
            $disc[] = ['field' => 'Jami talabalar soni', 'severity' => 'high',
                'expected' => (string) $sysN, 'found' => (string) $scanN,
                'note' => 'Talabalar soni mos kelmaydi.'];
        }

        // Imzolar + muhr.
        $sig = [
            'oqituvchi' => (bool) ($scan['signatures']['oqituvchi'] ?? false),
            'dekan' => (bool) ($scan['signatures']['dekan'] ?? false),
            'kafedra_mudiri' => (bool) ($scan['signatures']['kafedra_mudiri'] ?? false),
            'muhr' => (bool) ($scan['signatures']['muhr'] ?? false),
        ];
        $labels = [
            'oqituvchi' => "Imzo: o'qituvchi",
            'dekan' => 'Imzo: fakultet dekani',
            'kafedra_mudiri' => 'Imzo: kafedra mudiri',
            'muhr' => "Muhr (M.O')",
        ];
        foreach ($sig as $k => $ok) {
            if (!$ok) {
                $disc[] = ['field' => $labels[$k], 'severity' => 'high',
                    'expected' => "qo'yilgan", 'found' => "yo'q", 'note' => 'Skanerda topilmadi.'];
            }
        }

        return [
            'verdict' => empty($disc) ? 'ok' : 'issues',
            'summary' => $this->buildSummary($disc, $sysN, $scanN),
            'discrepancies' => $disc,
            'signatures' => $sig,
        ];
    }

    /**
     * Ro'yxatdagi xalqaro fakultet talabalari (ID raqami => true).
     */
    private function internationalStudentIds(array $talabalar): array
    {
        $ids = array_values(array_filter(array_map(fn ($t) => $this->digits($t['student_id'] ?? ''), $talabalar)));
        if (empty($ids)) {
            return [];
        }

        return Student::whereIn('student_id_number', $ids)
            ->get(['student_id_number', 'department_name'])
            ->filter(fn ($s) => $s->isInternationalFaculty())
            ->mapWithKeys(fn ($s) => [$this->digits($s->student_id_number) => true])
            ->all();
    }

    /** Faqat raqamlarni qoldiradi (talaba ID solishtirish uchun). */
    private function digits($s): string
    {
        return preg_replace('/\D+/', '', (string) $s);
    }

    /** Matnli qiymatni songa o'giradi (vergul→nuqta), bo'sh bo'lsa null. */
    private function num($v): ?float
    {
        if ($v === null) {
            return null;
        }
        $v = str_replace(',', '.', trim((string) $v));
        if ($v === '' || $v === '-' || !is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    private function fmt($v): string
    {
        $n = $this->num($v);
        if ($n === null) {
            return (string) $v;
        }

        return ($n == (int) $n) ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /** Ikki sonni tolerantlik bilan solishtiradi; farq bo'lsa nomuvofiqlik qo'shadi. */
    private function cmpNum(array &$disc, string $field, $expected, $found, ?float $tol = null, string $sev = 'medium'): void
    {
        $tol ??= (float) $this->settings->get('tolerance');
        $e = $this->num($expected);
        $f = $this->num($found);

        if ($e === null && $f === null) {
            return;
        }
        // Ball 0 va skaner bo'sh (yoki aksincha) — odatda bir xil, belgilamaymiz.
        if ($e !== null && abs($e) < 0.001 && $f === null) {
            return;
        }
        if ($f !== null && abs($f) < 0.001 && $e === null) {
            return;
        }
        if ($e === null) {
            $disc[] = ['field' => $field, 'severity' => $sev, 'expected' => '—',
                'found' => $this->fmt($found), 'note' => "Tizimda yo'q, skanerda bor."];

            return;
        }
        if ($f === null) {
            $disc[] = ['field' => $field, 'severity' => $sev, 'expected' => $this->fmt($expected),
                'found' => '—', 'note' => 'Skanerda yozilmagan.'];

            return;
        }
        if (abs($e - $f) > $tol) {
            $disc[] = ['field' => $field, 'severity' => $sev, 'expected' => $this->fmt($expected),
                'found' => $this->fmt($found), 'note' => 'Qiymat mos kelmaydi.'];
        }
    }

    /** Matnli qiymatlarni (ECTS/baho) normallashtirib solishtiradi. */
    private function cmpStr(array &$disc, string $field, $expected, $found, string $sev = 'low'): void
    {
        $e = $this->canon($expected);
        $f = $this->canon($found);
        if ($e === '' && $f === '') {
            return;
        }
        if ($e !== $f) {
            $disc[] = ['field' => $field, 'severity' => $sev, 'expected' => (string) $expected,
                'found' => (string) $found, 'note' => 'Mos kelmaydi.'];
        }
    }

    private function canon($s): string
    {
        $s = mb_strtolower(trim((string) $s));

        return str_replace(['ʼ', 'ʻ', '‘', '’', '`', ' ', '-'], '', $s);
    }

    private function isSpecialBaho(string $b): bool
    {
        if ($b === '') {
            return true;
        }
        foreach (['kelmadi', 'qo', 'yilmadi', 'davomat'] as $k) {
            if (mb_strpos($b, $k) !== false) {
                return true;
            }
        }

        return false;
    }

    private function normIssue(array $d): array
    {
        $sev = $d['severity'] ?? 'medium';

        return [
            'field' => (string) ($d['field'] ?? ''),
            'severity' => in_array($sev, ['high', 'medium', 'low'], true) ? $sev : 'medium',
            'expected' => (string) ($d['expected'] ?? ''),
            'found' => (string) ($d['found'] ?? ''),
            'note' => (string) ($d['note'] ?? ''),
        ];
    }

    private function buildSummary(array $disc, int $sysN, int $scanN): string
    {
        if (empty($disc)) {
            return "Vedomost tizim ma'lumotiga to'liq mos keladi ({$sysN} talaba; ballar, o'zlashtirish va imzolar tekshirildi).";
        }
        $high = count(array_filter($disc, fn($d) => ($d['severity'] ?? '') === 'high'));

        return count($disc) . " ta nomuvofiqlik topildi ({$high} ta jiddiy). Tizimda {$sysN}, skanerda {$scanN} talaba.";
    }
}
