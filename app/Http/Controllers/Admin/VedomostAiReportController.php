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
     * Yig'ma oyna: belgilangan bir nechta AI tekshirgan vedomost — faqat muammoli qatorlar,
     * fan bo'yicha guruhlangan; muammosizlar bitta ro'yxatda. ids[] — index qatorlari
     * («12,13,14» — guruhchalar yozuvlari); bitta PDF'li guruhchalar bir marta ko'rsatiladi.
     */
    public function review(Request $request)
    {
        $this->checkAccess(self::VIEW_ROLES);
        $ids = collect((array) $request->input('ids', []))
            ->flatMap(fn ($v) => explode(',', (string) $v))
            ->map(fn ($v) => (int) $v)->filter()->unique()->values();
        abort_if($ids->isEmpty(), 404, 'Vedomost tanlanmagan.');

        $seen = [];
        $items = [];
        foreach (VedomostSubmission::whereIn('id', $ids)->get() as $v) {
            if (isset($seen[$v->id])) {
                continue;
            }
            $siblings = $this->siblings($v);
            foreach ($siblings as $sib) {
                $seen[$sib->id] = true;
            }
            $merged = $this->merge->aggregate($siblings)->first();
            $result = $v->ai_result ?? [];
            $checked = $v->ai_check_status === 'done' && ($result['version'] ?? 1) >= VedomostCheckEngine::VERSION;
            $report = $checked ? $result['report'] : null;
            $items[] = [
                'v' => $v,
                'merged' => $merged,
                'checked' => $checked,
                'report' => $report,
                'verdict' => $checked ? $report['verdict'] : null,
                'rows' => $checked ? array_values(array_filter($report['rows'], fn ($r) => ($r['status'] ?? 'ok') !== 'ok')) : [],
                'extra' => $checked ? ($report['extra'] ?? []) : [],
                'header' => $checked ? array_values(array_filter($report['header'] ?? [], fn ($h) => ($h['st'] ?? 'ok') !== 'ok')) : [],
                'gate' => app(\App\Services\VedomostAiDispatcher::class)->approvalGate($v),
                'canEdit' => $this->canEdit($v),
                'approvable' => in_array($v->status, [VedomostSubmission::STATUS_RECEIVED, VedomostSubmission::STATUS_REVIEWING], true),
            ];
        }

        // Tartib: muammolilar (XATO → tekshiring → noto'g'ri fayl) yuqorida, keyin tekshirilmaganlar, oxirida moslar.
        $rank = ['wrong_document' => 0, 'issues' => 1, 'review' => 2, null => 3, 'ok' => 4];
        usort($items, fn ($a, $b) => [$rank[$a['verdict']] ?? 3, $a['merged']->subject_name ?? '', $a['merged']->group_name ?? '']
            <=> [$rank[$b['verdict']] ?? 3, $b['merged']->subject_name ?? '', $b['merged']->group_name ?? '']);

        $checked = array_values(array_filter($items, fn ($i) => $i['checked']));
        abort_if(empty($checked), 404, "Tanlanganlar orasida AI tekshirgan vedomost yo'q.");

        $history = VedomostSubmissionLog::whereIn('vedomost_submission_id', array_map(fn ($i) => $i['v']->id, $checked))
            ->where('action', 'ai_edit')
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();
        $labelOf = [];
        foreach ($items as $i) {
            $labelOf[$i['v']->id] = ($i['merged']->subject_name ?? $i['v']->subject_name) . ' · ' . ($i['merged']->group_name ?? $i['v']->group_name);
        }
        foreach ($history as $log) {
            $log->note = '[' . ($labelOf[$log->vedomost_submission_id] ?? '') . "]\n" . $log->note;
        }

        return view('admin.vedomost-submission.ai-report', [
            'v' => $checked[0]['v'],
            'merged' => (object) ['group_name' => count($items) . ' ta vedomost', 'subject_name' => "yig'ma tekshiruv"],
            'report' => $this->combineReports($checked),
            'edits' => [],
            'labels' => $this->engine->fieldLabels(),
            'canEdit' => collect($checked)->contains('canEdit', true),
            'history' => $history,
            'model' => null,
            'multi' => array_map(fn ($i) => [
                'id' => $i['v']->id,
                'label' => $labelOf[$i['v']->id],
                'form' => VedomostSubmission::formLabel($i['v']->form_type),
                'checked' => $i['checked'],
                'verdict' => $i['verdict'],
                'summary' => $i['checked'] ? $i['report']['summary'] : match ($i['v']->ai_check_status) {
                    'queued' => 'AI tekshiruvi navbatda', 'running' => 'AI tekshirmoqda', 'error' => 'AI xatosi',
                    'done' => 'Eski formatdagi natija — qayta tekshiring', default => 'AI tekshirmagan',
                },
                'status' => $i['v']->status,
                'gate' => $i['gate'],
                'approvable' => $i['approvable'],
                'canEdit' => $i['canEdit'],
                'save' => route('admin.vedomost-submission.ai-report.edits', $i['v']->id),
                'report' => route('admin.vedomost-submission.ai-report', $i['v']->id),
                'pdf' => $i['v']->pdf_path ? route('admin.vedomost-submission.file', [$i['v']->id, 'pdf']) : null,
                'show' => route('admin.vedomost-submission.show', $i['v']->id),
            ], $items),
            'canApprove' => in_array(session('active_role', ''), self::EDIT_ROLES, true),
        ]);
    }

    /**
     * Bir nechta hisobotni bitta jadvalga: qator kalitlari «vedomost id|kalit» (tuzatish o'z
     * vedomostiga saqlanadi), har qatorda manba (fan · guruh) va o'sha vedomostning OSKE/Test
     * ishlatilishi; sarlavha tekshiruvlari va ogohlantirishlar manba nomi bilan.
     */
    private function combineReports(array $items): array
    {
        $rank = ['ok' => 0, 'review' => 1, 'issues' => 2, 'wrong_document' => 3];
        $out = ['version' => VedomostCheckEngine::VERSION, 'verdict' => 'ok', 'rows' => [],
            'counts' => ['total' => 0, 'ok' => 0, 'bad' => 0, 'ocr' => 0, 'missing' => 0, 'nopdf' => 0, 'extra' => 0, 'edited' => 0, 'sys_edited' => 0], 'extra' => [],
            'issues' => [], 'header' => [], 'signatures' => [], 'scan_header' => [], 'meta' => [],
            'weights' => ['jb' => 0, 'mt' => 0, 'on' => 0, 'oski' => 0, 'test' => 0],
            'use' => ['oski' => false, 'test' => false, 'tm_oski' => false, 'tm_test' => false]];
        foreach ($items as $i) {
            $vid = $i['v']->id;
            $r = $i['report'];
            $src = ($i['merged']->subject_name ?? $i['v']->subject_name) . ' · ' . ($i['merged']->group_name ?? $i['v']->group_name);
            if (($rank[$r['verdict']] ?? 0) > ($rank[$out['verdict']] ?? 0)) {
                $out['verdict'] = $r['verdict'];
            }
            foreach ($r['counts'] as $k => $n) {
                $out['counts'][$k] = ($out['counts'][$k] ?? 0) + (int) $n;
            }
            foreach ($out['use'] as $k => $on) {
                $out['use'][$k] = $on || !empty($r['use'][$k]);
            }
            $w = $r['weights'] ?? [];
            if (($w['on'] ?? 0) > 0) {
                $out['weights']['on'] = $w['on'];
            }
            foreach ($r['rows'] as $row) {
                $row['vid'] = $vid;
                $row['src'] = $src;
                $row['use'] = $r['use'] ?? null;
                $row['ro'] = !$i['canEdit'];
                $row['mk'] = $vid . '|' . ($row['id'] ?? '');
                $row['key'] = $i['canEdit'] && !empty($row['key']) ? $vid . '|' . $row['key'] : null;
                $out['rows'][] = $row;
            }
            foreach ($r['extra'] ?? [] as $e) {
                $e['key'] = $i['canEdit'] && !empty($e['key']) ? $vid . '|' . $e['key'] : null;
                $e['note'] = $src . ' · ' . ($e['note'] ?? '');
                $out['extra'][] = $e;
            }
            foreach ($r['issues'] ?? [] as $is) {
                $is['m'] = $src . ': ' . $is['m'];
                $out['issues'][] = $is;
            }
            foreach ($r['header'] ?? [] as $h) {
                $h['k'] = $src . ' — ' . $h['k'];
                $out['header'][] = $h;
            }
        }
        $c = $out['counts'];
        $out['summary'] = count($items) . ' ta vedomost, ' . ($c['total'] ?? 0) . ' talaba: '
            . ($c['bad'] ?? 0) . ' XATO, ' . ($c['ocr'] ?? 0) . " PDFdan tekshiring, " . (($c['nopdf'] ?? 0) + ($c['missing'] ?? 0) + ($c['extra'] ?? 0)) . " ro'yxat farqi.";

        return $out;
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
            'system' => 'nullable|array',
            'system.*' => 'array',
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

        return app(VedomostAiReportExcel::class)->download($result['report'], "tekshiruv_{$name}.xlsx");
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

        // Mark qiymatlari — faqat shu hisobotda; sabab majburiy (tarixga yoziladi).
        $byId = collect($result['report']['rows'] ?? [])->keyBy('id');
        foreach (($incoming['system'] ?? []) as $sid => $cols) {
            $sid = (string) $sid;
            $row = $byId[$sid] ?? null;
            if (!$row || !is_array($cols)) {
                continue;
            }
            foreach ($cols as $col => $e) {
                if (!isset(VedomostCheckEngine::SYSTEM_EDITABLE[$col])) {
                    continue;
                }
                $label = VedomostCheckEngine::VT_HEADERS[$col] ?? $col;
                $orig = $row['sys_orig'][$col] ?? ($row['vt'][$col] ?? null);
                $prev = $edits['system'][$sid][$col]['v'] ?? $orig;
                if ($e === null) {
                    unset($edits['system'][$sid][$col]);
                    $new = $orig;
                    $why = '';
                } else {
                    $val = is_array($e) ? ($e['v'] ?? null) : $e;
                    $why = trim((string) (is_array($e) ? ($e['why'] ?? '') : ''));
                    $val = is_string($val) ? str_replace(',', '.', trim($val)) : $val;
                    if (!is_numeric($val)) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['system' => "{$row['fio']} — {$label}: son kiriting."]);
                    }
                    if (mb_strlen($why) < 3) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['system' => "{$row['fio']} — {$label}: Mark qiymatini o'zgartirish sababini yozing."]);
                    }
                    $edits['system'][$sid][$col] = ['v' => (float) $val, 'why' => mb_substr($why, 0, 300)];
                    $new = (float) $val;
                }
                if ((string) $prev !== (string) $new) {
                    $changes[] = "Mark qiymati (faqat hisobotda) {$row['fio']} — {$label}: " . $this->engine->f($prev) . ' → ' . $this->engine->f($new)
                        . ($why !== '' ? " · sabab: {$why}" : ' (asl qiymat)');
                }
            }
            if (empty($edits['system'][$sid])) {
                unset($edits['system'][$sid]);
            }
        }
        if (empty($edits['system'])) {
            unset($edits['system']);
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
