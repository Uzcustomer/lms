<?php

namespace App\Services;

use App\Models\Student;
use App\Models\VedomostSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Yuklangan vedomostni (skaner PDF) tizim ma'lumoti bilan solishtiradi:
 * VedomostScanReader skanerni Gemini orqali o'qiydi, VedomostCheckEngine esa
 * Python "vedomost_avto" qoidalari bo'yicha hisobot quradi.
 *
 * ai_result ichida expected (tizim), scan (o'qilgan), context va report saqlanadi —
 * xodim PDF qiymatini tuzatganda hisobot AI'siz qayta quriladi.
 *
 * Faqat tavsiya — yakuniy qarorni registrator ofisi xodimi qiladi.
 */
class VedomostAiChecker
{
    public function __construct(
        private YnQaydnomaDataService $qaydnoma,
        private VedomostMergeService $merge,
        private VedomostScanReader $reader,
        private VedomostCheckEngine $engine,
        private VedomostAiSettings $settings
    ) {
    }

    public static function isConfigured(): bool
    {
        return VedomostGeminiClient::isConfigured();
    }

    /**
     * To'liq tekshiruv: skanerni o'qish + hisobot.
     */
    public function check(VedomostSubmission $v): array
    {
        if (!$v->pdf_path || !Storage::disk('public')->exists($v->pdf_path)) {
            throw new RuntimeException('Skaner PDF topilmadi.');
        }

        // Birlashtirilgan vedomost: bitta o'zak guruh × o'zak fan uchun bitta varaq,
        // unda barcha guruhcha/variant talabalari jamlangan.
        $expected = $this->buildMergedExpected($v);
        if (!$expected) {
            throw new RuntimeException('Tizimda bu guruh/fan uchun YN qaydnoma ma\'lumoti topilmadi.');
        }

        $scan = $this->reader->read(Storage::disk('public')->path($v->pdf_path), $v->id);
        $context = $this->buildContext($v, $expected, $scan);

        return $this->assemble($expected, $scan, $context, []);
    }

    /**
     * Xodim tuzatishlari bilan hisobotni qayta quradi (AI chaqirilmaydi).
     */
    public function recompute(array $aiResult, array $edits): array
    {
        if (($aiResult['version'] ?? 1) < VedomostCheckEngine::VERSION || empty($aiResult['scan'])) {
            throw new RuntimeException("Bu natija eski formatda — avval vedomostni qayta tekshiring.");
        }

        return $this->assemble($aiResult['expected'], $aiResult['scan'], $aiResult['context'] ?? [], $edits)
            + ['checked_model' => $aiResult['model'] ?? null];
    }

    private function assemble(array $expected, array $scan, array $context, array $edits): array
    {
        $report = $this->engine->build($expected, $scan, $edits, $this->settings->all(), $context);

        return [
            'version' => VedomostCheckEngine::VERSION,
            'verdict' => $report['verdict'],
            'summary' => $report['summary'],
            'model' => $scan['meta']['model'] ?? $this->settings->get('model'),
            'expected' => $expected,
            'scan' => $scan,
            'context' => $context,
            'edits' => $edits,
            'report' => $report,
        ];
    }

    /**
     * Tashqi ma'lumotlar: kafedra mudiri, xalqaro fakultet talabalari,
     * Test markazi natijalari va (ortiqcha talabalar uchun) kontingent.
     */
    private function buildContext(VedomostSubmission $v, array $expected, array $scan): array
    {
        $sysIds = array_values(array_filter(array_map(
            fn ($t) => VedomostScanReader::digits($t['student_id'] ?? ''),
            $expected['talabalar'] ?? []
        )));
        $scanIds = array_values(array_filter(array_map(fn ($r) => VedomostScanReader::digits($r['id'] ?? ''), $scan['rows'] ?? [])));

        return [
            'kafedra_mudiri' => $v->kafedra_mudiri_name,
            'international' => $this->internationalIds($sysIds),
            'test_center' => $this->testCenter($sysIds, $expected, VedomostSubmission::formLabel($v->form_type)),
            'kontingent' => $this->kontingent(array_values(array_diff($scanIds, $sysIds))),
        ];
    }

    private function internationalIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return Student::whereIn('student_id_number', $ids)
            ->get(['student_id_number', 'department_name'])
            ->filter(fn ($s) => $s->isInternationalFaculty())
            ->mapWithKeys(fn ($s) => [VedomostScanReader::digits($s->student_id_number) => true])
            ->all();
    }

    /**
     * Test markazi (hemis_quiz_results): talaba ID → ['oski'|'test' => [grade, dalolat, date]].
     * Fan: fan_id = subject_id yoki nom o'xshashligi ≥ 0.85; shakl: vedomost shakli bilan bir xil.
     */
    private function testCenter(array $ids, array $expected, string $formLabel): array
    {
        if (empty($ids) || !Schema::hasTable('hemis_quiz_results')) {
            return [];
        }

        $q = DB::table('hemis_quiz_results')
            ->whereIn('student_id', $ids)
            ->where('is_active', 1)
            ->select('student_id', 'fan_id', 'fan_name', 'quiz_type', 'shakl', 'attempt_name', 'grade', 'date_finish');
        if (Schema::hasColumn('hemis_quiz_results', 'not_actual')) {
            $q->where(fn ($w) => $w->where('not_actual', false)->orWhereNull('not_actual'));
        }

        $subjectId = (string) ($expected['subject_id'] ?? '');
        $fan = (string) ($expected['fan'] ?? '');
        $base = $this->shaklBase($formLabel);

        $best = [];
        foreach ($q->get() as $r) {
            $fanOk = ($subjectId !== '' && (string) $r->fan_id === $subjectId)
                || VedomostNames::subjectScore($r->fan_name, $fan) >= 0.85;
            if (!$fanOk || $this->shaklBase((string) $r->shakl) !== $base) {
                continue;
            }
            $type = mb_strtolower((string) $r->quiz_type);
            $kind = str_contains($type, 'oski') || str_contains($type, 'oske') ? 'oski' : (str_contains($type, 'test') ? 'test' : null);
            if ($kind === null || $r->grade === null) {
                continue;
            }
            $dal = str_contains(mb_strtolower($r->attempt_name . ' ' . $r->shakl), 'dalolat');
            $sid = VedomostScanReader::digits($r->student_id);
            $cur = $best[$sid][$kind] ?? null;
            // Dalolatnomasiz natija afzal; keyin eng so'nggisi (Python check_tm).
            $rank = [$dal ? 0 : 1, (string) $r->date_finish];
            if ($cur === null || $rank > $cur['_rank']) {
                $best[$sid][$kind] = ['grade' => (float) $r->grade, 'dalolat' => $dal, 'date' => (string) $r->date_finish, '_rank' => $rank];
            }
        }

        foreach ($best as &$kinds) {
            foreach ($kinds as &$rec) {
                unset($rec['_rank']);
            }
        }

        return $best;
    }

    private function shaklBase(string $s): string
    {
        $t = preg_replace('/[^a-z0-9]/', '', mb_strtolower(VedomostNames::latin($s)));
        $t = preg_replace('/shakl$/', '', $t);
        $t = preg_replace('/^(\d+[ab]?)qoshimcha.*/', '$1qoshimcha', $t);

        return $t !== '' ? $t : '12';
    }

    /** Ortiqcha (guruhda yo'q) talabalar uchun kontingent ma'lumoti. */
    private function kontingent(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return Student::whereIn('student_id_number', $ids)
            ->get(['student_id_number', 'full_name', 'group_name', 'student_status_code'])
            ->mapWithKeys(fn ($s) => [VedomostScanReader::digits($s->student_id_number) => [
                'fio' => $s->full_name, 'group' => $s->group_name, 'status' => (string) $s->student_status_code,
            ]])
            ->all();
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
}
