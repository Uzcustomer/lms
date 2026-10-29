<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VedomostSubmission;
use App\Models\VedomostSubmissionLog;
use App\Services\VedomostAiChecker;
use App\Services\VedomostAiReportExcel;
use App\Services\VedomostCheckEngine;
use App\Services\VedomostMergeService;
use Illuminate\Http\Request;

/**
 * AI tekshiruv natijasi — Excel'ga o'xshash hisobot oynasi (yangi oynada ochiladi).
 *
 * Xodim PDFdan o'qilgan qiymatlarni tuzatadi; tizimdagi baholar (jurnal/HEMIS)
 * bu yerdan HECH QACHON o'zgarmaydi. Tuzatish saqlanganda hisobot AI'siz qayta
 * quriladi va har bir o'zgarish jarayon tarixiga yoziladi.
 */
class VedomostAiReportController extends Controller
{
    private const VIEW_ROLES = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi', 'oquv_prorektori'];

    private const EDIT_ROLES = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];

    public function __construct(
        private VedomostAiChecker $checker,
        private VedomostCheckEngine $engine,
        private VedomostMergeService $merge
    ) {
    }

    public function show($id)
    {
        $this->checkAccess(self::VIEW_ROLES);
        $v = VedomostSubmission::findOrFail($id);
        $result = $v->ai_result ?? [];

        if ($v->ai_check_status !== 'done' || ($result['version'] ?? 1) < VedomostCheckEngine::VERSION) {
            return redirect()->route('admin.vedomost-submission.show', $v->id)
                ->with('error', "Bu vedomost uchun yangi formatdagi AI natijasi yo'q — avval \"AI tekshirish\"ni bosing.");
        }

        $merged = $this->merge->aggregate($this->merge->siblingsOf($v))->first();
        $history = VedomostSubmissionLog::where('vedomost_submission_id', $v->id)
            ->where('action', 'ai_edit')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return view('admin.vedomost-submission.ai-report', [
            'v' => $v,
            'merged' => $merged,
            'report' => $result['report'],
            'edits' => $result['edits'] ?? [],
            'labels' => $this->engine->fieldLabels(),
            'canEdit' => $this->canEdit($v),
            'history' => $history,
            'model' => $result['model'] ?? null,
        ]);
    }

    /**
     * Tuzatishlarni saqlash. Kiruvchi: {rows:{r0:{jb_pct:"78"|null}}, header:{}, weights:{}, signatures:{}}.
     * null — tuzatishni bekor qilish (AI o'qigan asl qiymatga qaytish).
     */
    public function saveEdits(Request $request, $id)
    {
        $this->checkAccess(self::EDIT_ROLES);
        $v = VedomostSubmission::findOrFail($id);
        if (!$this->canEdit($v)) {
            return response()->json(['message' => "Tasdiqlangan vedomost natijasini o'zgartirib bo'lmaydi."], 422);
        }
        $result = $v->ai_result ?? [];
        if (($result['version'] ?? 1) < VedomostCheckEngine::VERSION) {
            return response()->json(['message' => 'Natija eski formatda — avval qayta tekshiring.'], 422);
        }

        $incoming = $request->validate([
            'rows' => 'nullable|array',
            'rows.*' => 'array',
            'header' => 'nullable|array',
            'weights' => 'nullable|array',
            'signatures' => 'nullable|array',
        ]);

        [$edits, $changes] = $this->mergeEdits($result, $incoming);
        if (empty($changes)) {
            return response()->json(['message' => "O'zgarish yo'q.", 'changes' => 0]);
        }

        $new = $this->checker->recompute($result, $edits);
        foreach ($this->siblings($v) as $sib) {
            $sib->update([
                'ai_result' => $new,
                'ai_verdict' => $new['verdict'],
                'ai_summary' => $new['summary'],
            ]);
        }

        VedomostSubmissionLog::create([
            'vedomost_submission_id' => $v->id,
            'action' => 'ai_edit',
            'from_status' => $v->status,
            'to_status' => $v->status,
            'note' => implode("\n", $changes),
            'user_id' => auth()->id(),
            'user_name' => $this->userName(),
        ]);

        return response()->json([
            'message' => count($changes) . " ta o'zgarish saqlandi.",
            'changes' => count($changes),
            'verdict' => $new['verdict'],
            'summary' => $new['summary'],
        ]);
    }

    public function excel($id)
    {
        $this->checkAccess(self::VIEW_ROLES);
        $v = VedomostSubmission::findOrFail($id);
        $result = $v->ai_result ?? [];
        abort_if(($result['version'] ?? 1) < VedomostCheckEngine::VERSION, 404, "Yangi formatdagi natija yo'q.");

        $name = preg_replace('/[^\w\-]+/u', '_', ($v->group_name ?? 'guruh') . '_' . ($v->subject_name ?? 'fan'));

        return app(VedomostAiReportExcel::class)->download($result['report'], $this->engine->fieldLabels(), "tekshiruv_{$name}.xlsx");
    }

    /**
     * Kiruvchi tuzatishlarni mavjudlariga qo'shadi; asl (AI o'qigan) qiymatga teng
     * yoki null bo'lsa — tuzatish olib tashlanadi. Tarix uchun o'qiladigan matn qaytaradi.
     *
     * @return array{0: array, 1: array}
     */
    private function mergeEdits(array $result, array $incoming): array
    {
        $edits = $result['edits'] ?? [];
        $scan = $result['scan'] ?? [];
        $labels = $this->engine->fieldLabels();
        $changes = [];
        $rowsByKey = [];
        foreach (array_values($scan['rows'] ?? []) as $i => $r) {
            $rowsByKey['r' . $i] = $r;
        }
        $reportRows = collect($result['report']['rows'] ?? [])->keyBy('key');

        foreach (($incoming['rows'] ?? []) as $key => $fields) {
            if (!isset($rowsByKey[$key])) {
                continue;
            }
            $who = $reportRows[$key]['fio'] ?? ($rowsByKey[$key]['fio'] ?? $key);
            foreach ($fields as $f => $val) {
                if (!in_array($f, VedomostCheckEngine::EDITABLE, true)) {
                    continue;
                }
                $orig = (string) ($rowsByKey[$key][$f] ?? '');
                $prev = $edits['rows'][$key][$f] ?? $orig;
                $val = $val === null ? null : trim((string) $val);
                if ($val === null || $val === $orig) {
                    unset($edits['rows'][$key][$f]);
                    $new = $orig;
                } else {
                    $edits['rows'][$key][$f] = $val;
                    $new = $val;
                }
                if ((string) $prev !== (string) $new) {
                    $changes[] = "{$who} — " . ($labels[$f] ?? $f) . ': ' . ($prev === '' ? '∅' : $prev) . ' → ' . ($new === '' ? '∅' : $new)
                        . ($new === $orig ? ' (asl qiymat)' : '');
                }
            }
            if (empty($edits['rows'][$key])) {
                unset($edits['rows'][$key]);
            }
        }
        if (empty($edits['rows'])) {
            unset($edits['rows']);
        }

        $sections = [
            'header' => [VedomostCheckEngine::HEADER_EDITABLE, $scan['header'] ?? [], 'Sarlavha'],
            'weights' => [['jb', 'mt', 'on', 'oski', 'test'], $scan['weights'] ?? [], 'Vazn'],
            'signatures' => [['oqituvchi', 'dekan', 'kafedra_mudiri', 'muhr'], $scan['signatures'] ?? [], 'Imzo'],
        ];
        foreach ($sections as $sec => [$allowed, $origs, $title]) {
            foreach (($incoming[$sec] ?? []) as $f => $val) {
                if (!in_array($f, $allowed, true)) {
                    continue;
                }
                $orig = $origs[$f] ?? null;
                $norm = fn ($x) => is_array($x) ? implode(', ', $x) : (is_bool($x) ? ($x ? 'bor' : "yo'q") : (string) $x);
                if ($sec === 'signatures' && $val !== null) {
                    $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                }
                $prev = $edits[$sec][$f] ?? $orig;
                if ($val === null || $norm($val) === $norm($orig)) {
                    unset($edits[$sec][$f]);
                    $new = $orig;
                } else {
                    $edits[$sec][$f] = $val;
                    $new = $val;
                }
                if ($norm($prev) !== $norm($new)) {
                    $changes[] = "{$title}: {$f}: " . ($norm($prev) === '' ? '∅' : $norm($prev)) . ' → ' . ($norm($new) === '' ? '∅' : $norm($new));
                }
            }
            if (empty($edits[$sec])) {
                unset($edits[$sec]);
            }
        }

        return [$edits, $changes];
    }

    private function siblings(VedomostSubmission $v)
    {
        $s = $this->merge->siblingsOf($v);
        if (!$s->contains('id', $v->id)) {
            $s->push($v);
        }

        return $s;
    }

    private function canEdit(VedomostSubmission $v): bool
    {
        return in_array(session('active_role', ''), self::EDIT_ROLES, true)
            && $v->status !== VedomostSubmission::STATUS_APPROVED;
    }

    private function checkAccess(array $roles): void
    {
        if (!auth()->user() || !in_array(session('active_role', ''), $roles, true)) {
            abort(403, "Bu sahifani ko'rish huquqi yo'q.");
        }
    }

    private function userName(): string
    {
        $u = auth()->user();

        return $u->name ?? $u->full_name ?? $u->short_name ?? 'Foydalanuvchi';
    }
}
