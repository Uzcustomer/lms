<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarkingSystemScore;
use App\Models\VedomostAiKey;
use App\Models\VedomostAiRequest;
use App\Models\VedomostSubmission;
use App\Services\VedomostAiDispatcher;
use App\Services\VedomostAiSettings;
use App\Services\VedomostGeminiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Vedomost topshirish → AI sozlamalari: Gemini kalitlari, model, limitlar,
 * tekshiruv qoidalari va statistika.
 *
 * Sahifa va sozlamalar — admin + registrator ofisi; kalitlar — faqat admin.
 */
class VedomostAiSettingsController extends Controller
{
    private const VIEW_ROLES = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];

    private const KEY_ROLES = ['superadmin', 'admin', 'kichik_admin'];

    public function __construct(
        private VedomostAiSettings $settings,
        private VedomostGeminiClient $gemini,
        private VedomostAiDispatcher $dispatcher
    ) {
    }

    public function index()
    {
        $this->checkAccess();

        $canManageKeys = $this->canManageKeys();
        $keys = $canManageKeys ? VedomostAiKey::orderByDesc('is_paid')->orderBy('id')->get() : collect();

        return view('admin.vedomost-submission.ai-settings', [
            'settings' => $this->settings->all(),
            'defaults' => VedomostAiSettings::defaults(),
            'canManageKeys' => $canManageKeys,
            'keys' => $keys,
            'keyUsage' => $this->keyUsage(),
            'stats' => $this->stats(),
            'models' => Cache::get('vedomost_ai_models', []),
            'configured' => VedomostGeminiClient::isConfigured(),
            'renderer' => \App\Services\VedomostScanReader::rendererAvailable(),
            'systemPass' => MarkingSystemScore::YN_JN_MT_LIMIT,
            'systemPassIntl' => MarkingSystemScore::INTERNATIONAL_VEDOMOST_PASS,
        ]);
    }

    /**
     * Jonli kuzatuv (5 s da yangilanadi): navbatdagi va tekshirilayotgan vedomostlar (oxirgi qadam
     * bilan), kalit ijaralari (qaysi ishchi qaysi kalitda), navbat holati va oxirgi Gemini so'rovlari.
     */
    public function live()
    {
        $this->checkAccess();

        $keys = VedomostAiKey::orderByDesc('is_paid')->orderBy('id')->get()->keyBy('id');
        $keyName = fn ($id) => $id && isset($keys[$id]) ? trim(($keys[$id]->label ?: 'Kalit #' . $id) . ' ' . $keys[$id]->key_hint) : ($id ? 'Kalit #' . $id : '—');
        $leases = $this->gemini->leases();
        $leaseBySub = collect($leases)->mapWithKeys(fn ($l, $kid) => [(int) ($l['submission'] ?? 0) => $kid])->all();

        $active = VedomostSubmission::whereIn('ai_check_status', ['queued', 'running'])
            ->orderByRaw("ai_check_status = 'running' desc")->orderBy('updated_at')
            ->limit(200)->get()
            ->unique(fn ($v) => $v->pdf_path ?: 'id' . $v->id)
            ->values()
            ->map(function ($v) use ($leaseBySub, $keyName) {
                $log = \App\Services\VedomostAiProgress::lines($v->pdf_path);
                $last = end($log) ?: null;
                $secs = $v->updated_at ? now()->diffInSeconds($v->updated_at, true) : null;

                return [
                    'id' => $v->id,
                    'url' => route('admin.vedomost-submission.show', $v->id),
                    'title' => trim(($v->group_name ?? '') . ' — ' . ($v->subject_name ?? '')),
                    'form' => VedomostSubmission::formLabel($v->form_type),
                    'status' => $v->ai_check_status,
                    'seconds' => $secs !== null ? (int) $secs : null,
                    'stuck' => $v->ai_check_status === 'running' && $secs !== null && $secs > VedomostAiDispatcher::STUCK_MINUTES * 60,
                    'step' => $last ? ['t' => $last['t'], 'm' => $last['m'], 'l' => $last['l']] : null,
                    'steps' => count($log),
                    'key' => isset($leaseBySub[$v->id]) ? $keyName($leaseBySub[$v->id]) : null,
                ];
            });

        $jobs = ['pending' => 0, 'reserved' => 0, 'delayed' => 0, 'oldest_wait' => null];
        if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
            $q = fn () => DB::table('jobs')->where('queue', \App\Jobs\CheckVedomostSubmissionWithAi::QUEUE);
            $now = now()->getTimestamp();
            $jobs['reserved'] = $q()->whereNotNull('reserved_at')->count();
            $jobs['pending'] = $q()->whereNull('reserved_at')->where('available_at', '<=', $now)->count();
            $jobs['delayed'] = $q()->whereNull('reserved_at')->where('available_at', '>', $now)->count();
            $oldest = $q()->whereNull('reserved_at')->where('available_at', '<=', $now)->min('available_at');
            $jobs['oldest_wait'] = $oldest ? $now - (int) $oldest : null;
        }

        $requests = VedomostAiRequest::orderByDesc('id')->limit(50)->get();
        $subNames = VedomostSubmission::whereIn('id', $requests->pluck('vedomost_submission_id')->filter()->unique()->all())
            ->get(['id', 'group_name', 'subject_name'])->keyBy('id');

        return response()->json([
            'now' => now()->format('H:i:s'),
            'workers' => 3,
            'active' => $active,
            'leases' => collect($leases)->map(fn ($l, $kid) => [
                'key' => $keyName($kid),
                'submission' => $l['submission'] ?? null,
                'since' => $l['at'] ?? null,
            ])->values(),
            'keys_total' => $keys->where('enabled', true)->count(),
            'jobs' => $jobs,
            // Navbatda vazifa bor-u, 2 daqiqadan beri hech biri olinmagan — ishchi ishlamayotgan bo'lishi mumkin.
            'worker_warning' => $jobs['pending'] > 0 && ($jobs['oldest_wait'] ?? 0) > 120 && $jobs['reserved'] === 0,
            'requests' => $requests->map(fn ($r) => [
                'at' => optional($r->created_at)->format('d.m H:i:s'),
                'submission' => $r->vedomost_submission_id,
                'url' => $r->vedomost_submission_id ? route('admin.vedomost-submission.show', $r->vedomost_submission_id) : null,
                'title' => isset($subNames[$r->vedomost_submission_id]) ? trim($subNames[$r->vedomost_submission_id]->group_name . ' — ' . $subNames[$r->vedomost_submission_id]->subject_name) : null,
                'key' => $keyName($r->vedomost_ai_key_id),
                'model' => $r->model,
                'purpose' => $r->purpose,
                'status' => $r->status,
                'http' => $r->http_status,
                'error_type' => $r->error_type,
                'error' => $r->error_message ? mb_substr($r->error_message, 0, 300) : null,
                'ms' => $r->latency_ms,
            ]),
        ]);
    }

    public function save(Request $request)
    {
        $this->checkAccess();

        $data = $request->validate([
            'model' => 'required|string|max:80',
            'fallback_model' => 'nullable|string|max:80',
            'timeout' => 'required|integer|min:10|max:600',
            'max_retries' => 'required|integer|min:0|max:5',
            'thinking_budget' => 'nullable|integer|min:0|max:32768',
            'bands' => 'required|integer|min:1|max:4',
            'daily_limit' => 'nullable|integer|min:1',
            'price_input_per_m' => 'nullable|numeric|min:0',
            'price_output_per_m' => 'nullable|numeric|min:0',
            'jn_mt_pass' => 'required|integer|min:0|max:100',
            'jn_mt_pass_international' => 'required|integer|min:0|max:100',
            'tolerance' => 'required|numeric|min:0|max:5',
            'oski_min' => 'required|integer|min:0|max:100',
            'test_min' => 'required|integer|min:0|max:100',
        ]);
        $data['auto_check'] = $request->boolean('auto_check');
        $data['require_ai_before_approve'] = $request->boolean('require_ai_before_approve');
        $data['allow_free_keys'] = $request->boolean('allow_free_keys');

        $this->settings->save($data);

        return back()->with('success', 'AI sozlamalari saqlandi.');
    }

    public function storeKey(Request $request)
    {
        $this->checkKeyAccess();

        $data = $request->validate([
            'label' => 'nullable|string|max:100',
            'api_key' => 'required|string|min:20|max:200',
            'is_paid' => 'nullable|boolean',
            'rpm' => 'nullable|integer|min:1|max:10000',
            'daily_limit' => 'nullable|integer|min:1',
        ]);
        $apiKey = trim($data['api_key']);

        $duplicate = VedomostAiKey::all()->contains(fn ($k) => $k->api_key === $apiKey);
        if ($duplicate) {
            return back()->with('error', "Bu kalit allaqachon qo'shilgan.");
        }

        VedomostAiKey::create([
            'label' => $data['label'] ?? null,
            'api_key' => $apiKey,
            'key_hint' => VedomostAiKey::hintFor($apiKey),
            'is_paid' => $request->boolean('is_paid'),
            'enabled' => true,
            'rpm' => $data['rpm'] ?? null,
            'daily_limit' => $data['daily_limit'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', "Kalit qo'shildi.");
    }

    public function updateKey(Request $request, $id)
    {
        $this->checkKeyAccess();
        $key = VedomostAiKey::findOrFail($id);

        $data = $request->validate([
            'label' => 'nullable|string|max:100',
            'rpm' => 'nullable|integer|min:1|max:10000',
            'daily_limit' => 'nullable|integer|min:1',
        ]);

        $key->update([
            'label' => $data['label'] ?? null,
            'is_paid' => $request->boolean('is_paid'),
            'enabled' => $request->boolean('enabled'),
            'rpm' => $data['rpm'] ?? null,
            'daily_limit' => $data['daily_limit'] ?? null,
        ]);

        return back()->with('success', 'Kalit sozlamalari saqlandi.');
    }

    public function destroyKey($id)
    {
        $this->checkKeyAccess();
        VedomostAiKey::findOrFail($id)->delete();

        return back()->with('success', "Kalit o'chirildi.");
    }

    /**
     * Gemini'dan modellar ro'yxatini yangilaydi (birinchi ishlaydigan kalit orqali).
     */
    public function refreshModels()
    {
        $this->checkAccess();

        $lastError = null;
        foreach ($this->gemini->usableKeys() as $key) {
            try {
                $models = $this->gemini->listModels($key);
                Cache::put('vedomost_ai_models', $models, now()->addDays(7));

                return back()->with('success', count($models) . ' ta model topildi.');
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        return back()->with('error', $lastError ?? "Ishlatsa bo'ladigan kalit yo'q.");
    }

    /**
     * PDF yuklangan, tasdiqlanmagan va AI tekshirmagan vedomostlarni navbatga qo'yadi.
     */
    public function queuePending(Request $request)
    {
        $this->checkAccess();

        if (!VedomostGeminiClient::isConfigured()) {
            return back()->with('error', "Avval Gemini API kaliti qo'shilishi kerak.");
        }

        $max = $request->filled('max') ? max(1, (int) $request->input('max')) : null;
        $count = $this->dispatcher->queuePending($max);

        return back()->with('success', $count
            ? "{$count} ta vedomost AI tekshiruv navbatiga qo'yildi."
            : "Navbatga qo'yiladigan vedomost yo'q.");
    }

    // --- Statistika ---

    private function stats(): array
    {
        $price = fn ($in, $out) => $this->cost((int) $in, (int) $out);

        $periods = [];
        foreach ([
            'today' => now()->startOfDay(),
            'week' => now()->subDays(6)->startOfDay(),
            'month' => now()->subDays(29)->startOfDay(),
            'all' => null,
        ] as $name => $from) {
            $row = VedomostAiRequest::query()
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->selectRaw("COUNT(*) as total,
                    SUM(CASE WHEN status = 'ok' THEN 1 ELSE 0 END) as ok,
                    SUM(CASE WHEN status <> 'ok' THEN 1 ELSE 0 END) as errors,
                    COALESCE(SUM(input_tokens), 0) as input_tokens,
                    COALESCE(SUM(output_tokens), 0) as output_tokens,
                    AVG(CASE WHEN status = 'ok' THEN latency_ms END) as avg_ms,
                    MAX(CASE WHEN status = 'ok' THEN latency_ms END) as max_ms")
                ->first();
            $periods[$name] = [
                'total' => (int) $row->total,
                'ok' => (int) $row->ok,
                'errors' => (int) $row->errors,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'avg_ms' => $row->avg_ms !== null ? (int) round($row->avg_ms) : null,
                'max_ms' => $row->max_ms !== null ? (int) $row->max_ms : null,
                'cost' => $price($row->input_tokens, $row->output_tokens),
            ];
        }

        $since = now()->subDays(29)->startOfDay();

        $errorTypes = VedomostAiRequest::where('created_at', '>=', $since)
            ->where('status', '<>', 'ok')
            ->selectRaw('error_type, COUNT(*) as cnt')
            ->groupBy('error_type')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'error_type')
            ->all();

        $models = VedomostAiRequest::where('created_at', '>=', $since)
            ->selectRaw("model, COUNT(*) as total,
                SUM(CASE WHEN status = 'ok' THEN 1 ELSE 0 END) as ok,
                AVG(CASE WHEN status = 'ok' THEN latency_ms END) as avg_ms")
            ->groupBy('model')
            ->orderByDesc('total')
            ->get();

        $daily = VedomostAiRequest::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw("DATE(created_at) as d, COUNT(*) as total,
                SUM(CASE WHEN status = 'ok' THEN 1 ELSE 0 END) as ok")
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('d')
            ->get()
            ->keyBy('d');
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $days[] = [
                'date' => $d,
                'total' => (int) ($daily[$d]->total ?? 0),
                'ok' => (int) ($daily[$d]->ok ?? 0),
            ];
        }

        // Natijalar — bitta PDF (guruhcha yozuvlari birgalikda) bir marta sanaladi.
        $results = DB::table('vedomost_submissions')
            ->whereNotNull('pdf_path')
            ->selectRaw("pdf_path,
                MAX(ai_check_status) as st,
                MAX(ai_verdict) as verdict,
                MAX(status) as status")
            ->groupBy('pdf_path')
            ->get();
        $outcomes = [
            'ok' => $results->where('st', 'done')->where('verdict', 'ok')->count(),
            'issues' => $results->where('st', 'done')->where('verdict', '<>', 'ok')->count(),
            'error' => $results->where('st', 'error')->count(),
            'in_progress' => $results->whereIn('st', ['queued', 'running'])->count(),
            'pending' => $results->where('st', 'none')
                ->whereIn('status', [VedomostSubmission::STATUS_RECEIVED, VedomostSubmission::STATUS_REVIEWING])
                ->count(),
        ];

        return compact('periods', 'errorTypes', 'models', 'days', 'outcomes');
    }

    /**
     * Har kalit bo'yicha bugungi va 7 kunlik so'rovlar.
     */
    private function keyUsage(): array
    {
        $today = VedomostAiRequest::where('created_at', '>=', now()->startOfDay())
            ->selectRaw("vedomost_ai_key_id as k, COUNT(*) as total,
                SUM(CASE WHEN status <> 'ok' THEN 1 ELSE 0 END) as errors")
            ->groupBy('vedomost_ai_key_id')
            ->get()
            ->keyBy('k');
        $week = VedomostAiRequest::where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('vedomost_ai_key_id as k, COUNT(*) as total')
            ->groupBy('vedomost_ai_key_id')
            ->pluck('total', 'k');

        $out = [];
        foreach ($today as $k => $row) {
            $out[$k]['today'] = (int) $row->total;
            $out[$k]['today_errors'] = (int) $row->errors;
        }
        foreach ($week as $k => $total) {
            $out[$k]['week'] = (int) $total;
        }

        return $out;
    }

    private function cost(int $inputTokens, int $outputTokens): ?float
    {
        $pin = $this->settings->get('price_input_per_m');
        $pout = $this->settings->get('price_output_per_m');
        if ($pin === null && $pout === null) {
            return null;
        }

        return round($inputTokens / 1e6 * (float) $pin + $outputTokens / 1e6 * (float) $pout, 4);
    }

    // --- Huquqlar ---

    private function checkAccess(): void
    {
        if (!auth()->user() || !in_array(session('active_role', ''), self::VIEW_ROLES, true)) {
            abort(403, "AI sozlamalarini faqat admin va registrator ofisi ko'ra oladi.");
        }
    }

    private function checkKeyAccess(): void
    {
        $this->checkAccess();
        if (!$this->canManageKeys()) {
            abort(403, 'API kalitlarni faqat admin boshqara oladi.');
        }
    }

    private function canManageKeys(): bool
    {
        return in_array(session('active_role', ''), self::KEY_ROLES, true);
    }
}
