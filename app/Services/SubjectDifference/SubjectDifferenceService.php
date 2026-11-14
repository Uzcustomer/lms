<?php

namespace App\Services\SubjectDifference;

use App\Models\SubjectDifferenceCheck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fanlar farqi tekshiruvi oqimi:
 *  1) transkript PDF(lar) yuklanadi va o'qiladi (matn qatlami bo'lsa qoida + AI, skaner bo'lsa AI);
 *  2) xodim o'quv reja va "qaysi semestrgacha"ni tanlaydi → bosqich (level) qo'shiladi:
 *     reja fanlari akkreditatsiyadan, moslashtirish qoida va AI bilan, ikkalasi saqlanadi;
 *  3) GPA < 2.4 bo'lsa xodim pastki kurs o'quv rejasini tanlab yangi bosqich qo'shadi.
 *
 * Yakuniy moslik: xodim tuzatgan bo'lsa — o'sha, aks holda AI (bo'lsa), aks holda qoida.
 */
class SubjectDifferenceService
{
    public const DISK = 'local';

    public function __construct(
        private TranscriptReader $reader,
        private PlanBuilder $plans,
        private RuleMatcher $rule,
        private AiMatcher $ai,
        private DifferenceCalculator $calc,
    ) {
    }

    /**
     * @param  UploadedFile[]  $files
     */
    public function create(array $files, array $scale, ?string $studentName, ?string $institution, $user): SubjectDifferenceCheck
    {
        $check = SubjectDifferenceCheck::create([
            'student_name' => $studentName,
            'source_institution' => $institution,
            'scale' => GradeScale::fromArray($scale)->toArray(),
            'status' => 'new',
            'created_by' => $user?->id,
            'created_by_name' => $user?->name ?? $user?->full_name ?? null,
        ]);

        $stored = [];
        foreach (array_values($files) as $i => $file) {
            $path = $file->storeAs('subject-differences/' . $check->id, ($i + 1) . '_' . Str::random(8) . '.pdf', self::DISK);
            $stored[] = [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'role' => $i === 0 ? 'Transkript' : 'Qo\'shimcha (ko\'chirma)',
            ];
        }
        $check->files = $stored;
        $check->status = 'reading'; // o'qish fon navbatida (ProcessSubjectDifferenceCheck)
        $check->save();

        return $check;
    }

    public function read(SubjectDifferenceCheck $check): void
    {
        try {
            $paths = array_map(fn ($f) => ['path' => Storage::disk(self::DISK)->path($f['path']), 'name' => $f['name']], $check->files ?? []);
            $res = $this->reader->read($paths);
            $res['source'] = $res['ai'] !== null ? 'ai' : 'text';
            $check->transcript = $res;
            $check->status = !empty($check->levels) ? 'checked' : 'read';
            $check->error = null;
            if (!$check->student_name && !empty($res['header']['student_name'])) {
                $check->student_name = $res['header']['student_name'];
            }
            if (!$check->source_institution && !empty($res['header']['institution'])) {
                $check->source_institution = $res['header']['institution'];
            }
        } catch (\Throwable $e) {
            $check->status = 'error';
            $check->error = mb_substr($e->getMessage(), 0, 1000);
        }
        $check->save();
    }

    public function addLevel(SubjectDifferenceCheck $check, int $curriculumId, int $upto, bool $useAi = true): array
    {
        $plan = $this->plans->build($curriculumId, $upto);
        if (!$plan) {
            throw new RuntimeException("Tanlangan o'quv rejada 1–{$upto}-semestrlar bo'yicha akkreditatsiya fanlari topilmadi.");
        }

        $level = [
            'id' => (string) Str::uuid(),
            'curriculum_id' => $curriculumId,
            'curriculum_name' => $this->plans->curriculumLabel($curriculumId),
            'upto' => $upto,
            'source' => $check->transcriptSource(),
            'plan' => $plan,
            'overrides' => [],
            'excluded' => [],
            'created_at' => now()->toDateTimeString(),
        ];
        $this->match($check, $level, $useAi);

        $levels = $check->levels ?? [];
        $levels[] = $level;
        $check->levels = $levels;
        $check->status = 'checked';
        $check->save();

        return $level;
    }

    /** Moslashtirishni qayta ishga tushiradi (xodim tuzatishlari saqlanadi). */
    public function rematch(SubjectDifferenceCheck $check, string $levelId, bool $useAi = true): void
    {
        $this->updateLevel($check, $levelId, function (array $level) use ($check, $useAi) {
            $level['source'] = $check->transcriptSource();
            // Reja qayta quriladi (yangi tartib, akkreditatsiyadagi o'zgarishlar); kalitlar
            // "fan-semestr" bo'lgani uchun xodim tuzatishlari saqlanadi.
            $plan = $this->plans->build((int) $level['curriculum_id'], (int) $level['upto']);
            if ($plan) {
                $level['plan'] = $plan;
            }
            $this->match($check, $level, $useAi);

            return $level;
        });
    }

    public function saveMapping(SubjectDifferenceCheck $check, string $levelId, array $overrides, array $excluded): void
    {
        $this->updateLevel($check, $levelId, function (array $level) use ($overrides, $excluded, $check) {
            $keys = array_column($level['plan'], 'key');
            $rows = $this->rows($check, $level);
            $clean = [];
            foreach ($overrides as $key => $tr) {
                if (!in_array((string) $key, $keys, true)) {
                    continue;
                }
                if ($tr === '' || $tr === null || $tr === 'auto') {
                    continue; // avtomatik (AI/qoida) moslikka qaytarildi
                }
                if ($tr === 'none') {
                    $clean[(string) $key] = null;
                } elseif (is_numeric($tr) && isset($rows[(int) $tr])) {
                    $clean[(string) $key] = (int) $tr;
                }
            }
            $level['overrides'] = $clean;
            $level['excluded'] = array_values(array_intersect($keys, array_map('strval', $excluded)));

            return $level;
        });
    }

    public function deleteLevel(SubjectDifferenceCheck $check, string $levelId): void
    {
        $check->levels = array_values(array_filter($check->levels ?? [], fn ($l) => $l['id'] !== $levelId));
        $check->save();
    }

    /**
     * Bosqich natijasi: yakuniy, faqat-qoida va faqat-AI hisoblari.
     */
    public function evaluate(SubjectDifferenceCheck $check, array $level): array
    {
        $rows = $this->rows($check, $level);
        $scale = GradeScale::fromArray($check->scale);
        $ruleMap = array_map(fn ($m) => $m['tr'], $level['rule'] ?? []);
        $aiMap = isset($level['ai']) && is_array($level['ai']) ? array_map(fn ($m) => $m['tr'], $level['ai']) : null;

        $auto = $aiMap ?? $ruleMap;
        $final = $auto;
        foreach ($level['overrides'] ?? [] as $key => $tr) {
            $final[$key] = $tr;
        }

        $excluded = $level['excluded'] ?? [];
        $result = $this->calc->calculate($level['plan'], $final, $rows, $scale, (int) $level['upto'], $excluded);
        $ruleRes = $this->calc->calculate($level['plan'], $ruleMap, $rows, $scale, (int) $level['upto'], $excluded);
        $aiRes = $aiMap !== null ? $this->calc->calculate($level['plan'], $aiMap, $rows, $scale, (int) $level['upto'], $excluded) : null;

        $disagree = 0;
        foreach ($result['rows'] as &$r) {
            $key = $r['key'];
            $r['rule_tr'] = $ruleMap[$key] ?? null;
            $r['rule_score'] = $level['rule'][$key]['score'] ?? null;
            $r['ai_tr'] = $aiMap[$key] ?? null;
            $r['ai_confidence'] = $level['ai'][$key]['confidence'] ?? null;
            $r['ai_reason'] = $level['ai'][$key]['reason'] ?? null;
            $r['overridden'] = array_key_exists($key, $level['overrides'] ?? []);
            $r['disagree'] = $aiMap !== null && ($r['rule_tr'] !== $r['ai_tr']);
            if ($r['disagree']) {
                $disagree++;
            }
        }
        unset($r);

        return $result + [
            'rule_gpa' => $ruleRes['gpa'],
            'ai_gpa' => $aiRes['gpa'] ?? null,
            'disagree' => $disagree,
            'transcript' => $rows,
        ];
    }

    /** Bosqichda ishlatilgan transkript qatorlari (bosqich yaratilgan paytdagi manba). */
    public function rows(SubjectDifferenceCheck $check, array $level): array
    {
        $t = $check->transcript ?? [];
        $source = $level['source'] ?? $check->transcriptSource();

        return $t[$source] ?? $check->transcriptRows();
    }

    /**
     * Matnli o'qish (pdftotext) bilan AI o'qishini qatorma-qator solishtiradi —
     * faqat PDF'da matn qatlami bo'lganda ikkalasi mavjud.
     *
     * @return array{rows: array, same: int, diff: int, only_text: int, only_ai: int}|null
     */
    public function compareSources(SubjectDifferenceCheck $check): ?array
    {
        $t = $check->transcript ?? [];
        if (empty($t['text']) || empty($t['ai'])) {
            return null;
        }
        $key = fn ($r) => ($r['file'] ?? 0) . '|' . ($r['semester'] ?? '') . '|' . $this->rule->normalize((string) $r['name']);
        $ai = [];
        foreach ($t['ai'] as $i => $r) {
            $ai[$key($r)][] = $i;
        }

        $rows = [];
        $stats = ['same' => 0, 'diff' => 0, 'only_text' => 0, 'only_ai' => 0];
        $usedAi = [];
        foreach ($t['text'] as $i => $r) {
            $k = $key($r);
            $j = isset($ai[$k]) ? array_shift($ai[$k]) : null;
            if ($j === null) {
                $rows[] = ['status' => 'only_text', 'text' => $r, 'ai' => null];
                $stats['only_text']++;
                continue;
            }
            $usedAi[$j] = true;
            $a = $t['ai'][$j];
            $same = (float) ($r['ball'] ?? -1) === (float) ($a['ball'] ?? -1) && (int) ($r['grade'] ?? -1) === (int) ($a['grade'] ?? -1)
                && (float) ($r['credit'] ?? -1) === (float) ($a['credit'] ?? -1);
            $rows[] = ['status' => $same ? 'same' : 'diff', 'text' => $r, 'ai' => $a];
            $stats[$same ? 'same' : 'diff']++;
        }
        foreach ($t['ai'] as $j => $a) {
            if (!isset($usedAi[$j])) {
                $rows[] = ['status' => 'only_ai', 'text' => null, 'ai' => $a];
                $stats['only_ai']++;
            }
        }

        return ['rows' => $rows] + $stats;
    }

    public function editTranscriptRow(SubjectDifferenceCheck $check, string $source, int $index, array $data): void
    {
        $t = $check->transcript ?? [];
        if (!isset($t[$source][$index])) {
            throw new RuntimeException('Transkript qatori topilmadi.');
        }
        $row = $t[$source][$index];
        $row['original'] ??= array_intersect_key($row, array_flip(['name', 'semester', 'ball', 'grade']));
        foreach (['name' => 'string', 'semester' => 'int', 'ball' => 'float', 'grade' => 'int'] as $f => $type) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $v = $data[$f];
            $row[$f] = ($v === '' || $v === null) ? null : match ($type) {
                'int' => (int) $v,
                'float' => (float) str_replace(',', '.', (string) $v),
                default => trim((string) $v),
            };
        }
        $row['edited'] = true;
        $t[$source][$index] = $row;
        $check->transcript = $t;
        $check->save();
    }

    public function setSource(SubjectDifferenceCheck $check, string $source): void
    {
        $t = $check->transcript ?? [];
        if (!in_array($source, ['ai', 'text'], true) || empty($t[$source])) {
            throw new RuntimeException('Bu manba bo\'yicha o\'qilgan qatorlar yo\'q.');
        }
        $t['source'] = $source;
        $check->transcript = $t;
        $check->save();
    }

    private function match(SubjectDifferenceCheck $check, array &$level, bool $useAi): void
    {
        $rows = $this->rows($check, $level);
        $level['rule'] = SameSubjectFill::apply($level['plan'], $this->rule->match($level['plan'], $rows));
        $level['ai'] = null;
        $level['ai_error'] = null;
        $level['ai_model'] = null;

        if ($useAi && TranscriptReader::aiConfigured()) {
            try {
                $res = $this->ai->match($level['plan'], $rows);
                $level['ai'] = SameSubjectFill::apply($level['plan'], $res['map']);
                $level['ai_model'] = $res['model'];
            } catch (\Throwable $e) {
                $level['ai_error'] = mb_substr($e->getMessage(), 0, 500);
            }
        } elseif ($useAi) {
            $level['ai_error'] = 'Gemini API kaliti sozlanmagan (Vedomost tekshirish → AI sozlamalari).';
        }
    }

    private function updateLevel(SubjectDifferenceCheck $check, string $levelId, callable $fn): void
    {
        $levels = $check->levels ?? [];
        $found = false;
        foreach ($levels as $i => $l) {
            if ($l['id'] === $levelId) {
                $levels[$i] = $fn($l);
                $found = true;
            }
        }
        if (!$found) {
            throw new RuntimeException('Bosqich topilmadi.');
        }
        $check->levels = $levels;
        $check->save();
    }
}
