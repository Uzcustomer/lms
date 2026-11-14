<x-app-layout>
@php
    $t = $check->transcript ?? [];
    $source = $check->transcriptSource();
    $rows = $check->transcriptRows();
    $files = $check->files ?? [];
    $fmt = fn ($v) => $v === null || $v === '' ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $trLabel = fn ($r) => $r ? (($r['semester'] ? $r['semester'] . '-sem · ' : '') . $r['name']) : '';
    $sourceLabels = ['ai' => 'AI (Gemini)', 'text' => 'Matn (pdftotext)'];
@endphp

    <div class="min-h-full bg-[#eef3f9] px-3 py-5 sm:px-5 lg:px-6"
         x-data="{ edit: null, openEdit(src, i, r) { this.edit = { source: src, index: i, name: r.name, semester: r.semester, ball: r.ball, grade: r.grade }; } }">
        <div class="mx-auto max-w-[1700px] space-y-4">

            <div class="rounded-2xl px-6 py-5 text-white shadow-lg" style="background: linear-gradient(135deg, #102a56 0%, #1e5da8 68%, #3181c8 100%) !important;">
                <a href="{{ route('admin.subject-differences.index') }}" class="text-xs text-white/70 hover:text-white">&larr; Fanlar farqi</a>
                <div class="mt-1 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-bold">{{ $check->student_name ?: 'Talaba' }}</h1>
                        <p class="mt-1 text-sm text-white/80">{{ $check->source_institution ?: 'Avvalgi OTM ko\'rsatilmagan' }} · {{ $scale->label() }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        @foreach($files as $i => $f)
                            <a href="{{ route('admin.subject-differences.file', [$check, $i]) }}" target="_blank"
                               class="rounded-lg bg-white/15 px-3 py-1.5 font-semibold hover:bg-white/25">📄 {{ $f['role'] }}: {{ \Illuminate\Support\Str::limit($f['name'], 30) }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            @if(session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            {{-- ───────────── Transkript ───────────── --}}
            <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" {{ empty($levels) ? 'open' : '' }}>
                <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3 bg-slate-50 px-4 py-3">
                    <div>
                        <h3 class="inline text-sm font-bold text-slate-800">Transkriptdan o'qilgan fanlar</h3>
                        <span class="ml-2 text-xs text-slate-600">{{ count($rows) }} qator · asosiy manba: {{ $sourceLabels[$source] ?? $source }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @foreach($t['files'] ?? [] as $fm)
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-slate-700">{{ \Illuminate\Support\Str::limit($fm['name'], 24) }}:
                                {{ $fm['has_text'] ? 'matnli PDF' : 'skaner (rasm)' }}
                                @if($fm['text_rows'] !== null) · matn {{ $fm['text_rows'] }} @endif
                                @if($fm['ai_rows'] ?? null) · AI {{ $fm['ai_rows'] }} @endif
                            </span>
                        @endforeach
                    </div>
                </summary>

                <div class="space-y-3 p-4">
                    @if($check->status === 'error')
                        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">Transkript o'qilmadi: {{ $check->error }}</div>
                    @endif
                    @if(!empty($t['ai_error']))
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">AI o'qishida xato: {{ $t['ai_error'] }}</div>
                    @endif
                    @if(!empty($t['header']['grading_scale']))
                        <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                            Transkriptdagi baholash tizimi: <b>{{ $t['header']['grading_scale'] }}</b> — tanlangan shkala: <b>{{ $scale->label() }}</b>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-2">
                        @if(!empty($t['text']) && !empty($t['ai']))
                            <form method="POST" action="{{ route('admin.subject-differences.source', $check) }}" class="flex items-center gap-2 text-xs">
                                @csrf
                                <span class="font-semibold text-slate-600">Moslashtirishda ishlatilsin:</span>
                                @foreach(['ai', 'text'] as $s)
                                    <button name="source" value="{{ $s }}" class="rounded-lg border px-2.5 py-1 font-semibold {{ $source === $s ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $sourceLabels[$s] }} ({{ count($t[$s]) }})</button>
                                @endforeach
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.subject-differences.reread', $check) }}" x-data="{busy:false}" @submit="busy=true" class="ml-auto">
                            @csrf
                            <button :disabled="busy" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60" x-text="busy ? 'O\'qilmoqda…' : 'Transkriptni qayta o\'qish'">Transkriptni qayta o'qish</button>
                        </form>
                    </div>

                    @if($sourceCompare)
                        <div class="rounded-xl border border-slate-200">
                            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                                <b class="text-slate-700">Matn ↔ AI o'qishi solishtirmasi:</b>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">bir xil {{ $sourceCompare['same'] }}</span>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700">farqli {{ $sourceCompare['diff'] }}</span>
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-slate-700">faqat matnda {{ $sourceCompare['only_text'] }}</span>
                                <span class="rounded-full bg-violet-100 px-2 py-0.5 text-violet-700">faqat AI'da {{ $sourceCompare['only_ai'] }}</span>
                            </div>
                            @php $diffRows = array_filter($sourceCompare['rows'], fn ($r) => $r['status'] !== 'same'); @endphp
                            @if($diffRows)
                                <table class="min-w-full text-xs">
                                    <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-3 py-1.5">Holat</th><th class="px-3 py-1.5">Matn: fan</th><th class="px-3 py-1.5">kredit · ball/baho</th><th class="px-3 py-1.5">AI: fan</th><th class="px-3 py-1.5">kredit · ball/baho</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($diffRows as $d)
                                            <tr>
                                                <td class="px-3 py-1.5">{{ ['diff' => 'farqli', 'only_text' => 'faqat matnda', 'only_ai' => 'faqat AI\'da'][$d['status']] }}</td>
                                                <td class="px-3 py-1.5">{{ $trLabel($d['text']) }}</td>
                                                <td class="px-3 py-1.5">@if($d['text']){{ $fmt($d['text']['credit']) }} · {{ $fmt($d['text']['ball']) }}/{{ $d['text']['grade'] ?? '—' }}@endif</td>
                                                <td class="px-3 py-1.5">{{ $trLabel($d['ai']) }}</td>
                                                <td class="px-3 py-1.5">@if($d['ai']){{ $fmt($d['ai']['credit']) }} · {{ $fmt($d['ai']['ball']) }}/{{ $d['ai']['grade'] ?? '—' }}@endif</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @endif

                    @if($rows)
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full text-xs">
                                <thead class="bg-slate-50 text-left font-semibold text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">#</th><th class="px-3 py-2">Semestr</th><th class="px-3 py-2">Fan nomi (transkript)</th>
                                        <th class="px-3 py-2 text-right">Yuklama/Kredit</th><th class="px-3 py-2 text-right">Ball/Baho</th>
                                        <th class="px-3 py-2 text-center">Baho (shkala)</th><th class="px-3 py-2">Fayl</th><th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($rows as $i => $r)
                                        @php $res = $scale->resolve($r); @endphp
                                        <tr class="{{ !empty($r['edited']) ? 'bg-blue-50/60' : '' }}">
                                            <td class="px-3 py-1.5 text-slate-500">{{ $i + 1 }}</td>
                                            <td class="px-3 py-1.5">{{ $r['semester'] ?? '—' }}</td>
                                            <td class="px-3 py-1.5 text-slate-800">{{ $r['name'] }} @if(!empty($r['edited']))<span class="text-[11px] text-blue-700">(tuzatilgan)</span>@endif</td>
                                            <td class="px-3 py-1.5 text-right">{{ $r['hours'] ?? '—' }} / {{ $fmt($r['credit'] ?? null) }}</td>
                                            <td class="px-3 py-1.5 text-right">{{ $fmt($r['ball'] ?? null) }} / {{ $r['grade'] ?? '—' }}@if(!empty($r['grade_text'])) <span class="text-slate-600">({{ $r['grade_text'] }})</span>@endif</td>
                                            <td class="px-3 py-1.5 text-center">
                                                <span class="font-bold {{ ($res['grade'] ?? 0) < 3 ? 'text-rose-600' : 'text-slate-800' }}">{{ $res['grade'] ?? '—' }}</span>
                                                @if($res['conflicts'])<span class="ml-1 cursor-help text-amber-600" title="{{ implode('; ', $res['conflicts']) }}">⚠</span>@endif
                                            </td>
                                            <td class="px-3 py-1.5 text-slate-500">{{ ($r['file'] ?? 0) + 1 }}</td>
                                            <td class="px-3 py-1.5 text-right">
                                                <button type="button" class="text-blue-600 hover:underline" @click="openEdit({{ \Illuminate\Support\Js::from($source) }}, {{ $i }}, {{ \Illuminate\Support\Js::from($r) }})">tuzatish</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </details>

            {{-- ───────────── Bosqichlar ───────────── --}}
            @foreach($levels as $n => $item)
                @php
                    $level = $item['level'];
                    $res = $item['result'];
                    $dec = $res['decision'];
                    $trRows = $res['transcript'];
                    $hasAi = $res['ai_gpa'] !== null;
                    $optLabel = fn ($ti, $tr) => \Illuminate\Support\Str::limit($trLabel($tr), 60) . ' · ' . $fmt($tr['ball'] ?? null) . '/' . ($tr['grade'] ?? '—');
                    $optList = collect($trRows)->map(fn ($tr, $ti) => [$ti, $optLabel($ti, $tr)])->values();
                    $overCount = collect($res['rows'])->where('overridden', true)->count();
                    $isElective = fn ($t) => \App\Services\SubjectDifference\DifferenceCalculator::isElective($t);
                    $title = (int) $level['upto'] <= 1
                        ? '1-kurs (2-semestrdan davom) tekshiruvi'
                        : $dec['kurs'] . '-kursga qabul tekshiruvi';
                    $gpaPct = min(100, max(0, $res['gpa'] / 5 * 100));
                    $limitPct = \App\Services\SubjectDifference\DifferenceCalculator::GPA_LIMIT / 5 * 100;
                @endphp
                <script>window.sdOpts = window.sdOpts || {}; window.sdOpts[{{ \Illuminate\Support\Js::from($level['id']) }}] = {{ \Illuminate\Support\Js::from($optList) }};</script>
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm" id="level-{{ $level['id'] }}"
                     x-data="{ filter: '{{ ($res['farq_count'] + $res['disagree']) > 0 ? 'review' : 'all' }}', dirty: 0 }">

                    {{-- Sarlavha --}}
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-slate-800 px-2 py-0.5 text-[11px] font-bold text-white">{{ $n + 1 }}-bosqich</span>
                                <h3 class="text-base font-bold text-slate-900">{{ $title }}</h3>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">1–{{ $level['upto'] }}-semestr</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-600">
                                {{ $level['curriculum_name'] }} · {{ count($level['plan']) }} ta fan (akkreditatsiya) · transkript: {{ $sourceLabels[$level['source'] ?? 'ai'] ?? '' }}
                                @if($level['ai_model'] ?? null) · {{ $level['ai_model'] }} @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <a href="{{ route('admin.subject-differences.levels.excel', [$check, $level['id']]) }}"
                               class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 font-semibold text-white hover:bg-emerald-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                Excel
                            </a>
                            <form method="POST" action="{{ route('admin.subject-differences.levels.rematch', [$check, $level['id']]) }}" x-data="{busy:false}" @submit="busy=true">
                                @csrf
                                <input type="hidden" name="use_ai" value="1">
                                <button :disabled="busy" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60" x-text="busy ? 'Moslashtirilmoqda…' : 'Qayta moslashtirish'">Qayta moslashtirish</button>
                            </form>
                            <form method="POST" action="{{ route('admin.subject-differences.levels.destroy', [$check, $level['id']]) }}" onsubmit="return confirm('Bosqich o\'chirilsinmi?')">
                                @csrf @method('DELETE')
                                <button class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-white px-3 font-semibold text-rose-600 hover:bg-rose-50">O'chirish</button>
                            </form>
                        </div>
                    </div>

                    {{-- Xulosa --}}
                    <div class="grid grid-cols-1 gap-3 p-5 lg:grid-cols-12">
                        <div class="flex items-start gap-3 rounded-xl border p-4 lg:col-span-5 {{ $dec['passed'] ? 'border-emerald-300 bg-emerald-50' : 'border-rose-300 bg-rose-50' }}">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white {{ $dec['passed'] ? 'bg-emerald-600' : 'bg-rose-600' }}" aria-hidden="true">
                                @if($dec['passed'])
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold {{ $dec['passed'] ? 'text-emerald-800' : 'text-rose-800' }}">Xulosa · {{ $dec['rule'] }}</div>
                                <div class="mt-0.5 text-base font-bold leading-snug {{ $dec['passed'] ? 'text-emerald-950' : 'text-rose-950' }}">{{ $dec['text'] }}</div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4 lg:col-span-4">
                            <div class="flex items-baseline justify-between">
                                <span class="text-xs font-semibold text-slate-600">Yakuniy GPA</span>
                                <span class="text-xs text-slate-600">{{ $fmt($res['total_product']) }} / {{ $fmt($res['total_credit']) }} kredit</span>
                            </div>
                            <div class="mt-1 text-3xl font-bold tabular-nums {{ $res['gpa'] >= \App\Services\SubjectDifference\DifferenceCalculator::GPA_LIMIT ? 'text-slate-900' : 'text-rose-600' }}">{{ number_format($res['gpa'], 3) }}</div>
                            @if((int) $level['upto'] > 1)
                                <div class="relative mt-2 h-2 rounded-full bg-slate-100" role="img" aria-label="GPA {{ number_format($res['gpa'], 2) }}, chegara 2.4">
                                    <div class="h-2 rounded-full {{ $res['gpa'] >= 2.4 ? 'bg-emerald-500' : 'bg-rose-500' }}" style="width: {{ $gpaPct }}%"></div>
                                    <div class="absolute -top-1 h-4 w-0.5 bg-slate-700" style="left: {{ $limitPct }}%"></div>
                                </div>
                                <div class="relative mt-1 h-4 text-[11px] text-slate-600">
                                    <span class="absolute -translate-x-1/2" style="left: {{ $limitPct }}%">2.4</span>
                                    <span class="absolute right-0">5.0</span>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4 lg:col-span-3">
                            <div class="text-xs font-semibold text-slate-600">Fanlar farqi</div>
                            <div class="mt-1 text-3xl font-bold tabular-nums text-rose-600">{{ $res['farq_count'] }} <span class="text-base font-semibold text-slate-600">fan</span></div>
                            <div class="mt-1 text-xs text-slate-600">{{ $fmt($res['farq_credit']) }} kredit o'zlashtirilishi kerak</div>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700 lg:col-span-12">
                            <span class="font-semibold text-slate-800">Usullar solishtirmasi:</span>
                            <span>Qoida bo'yicha GPA <b class="tabular-nums">{{ number_format($res['rule_gpa'], 3) }}</b></span>
                            <span>AI bo'yicha GPA <b class="tabular-nums text-violet-700">{{ $hasAi ? number_format($res['ai_gpa'], 3) : '—' }}</b></span>
                            <span>Kelishmagan qatorlar: <b class="{{ $res['disagree'] ? 'text-amber-700' : '' }}">{{ $res['disagree'] }}</b></span>
                            @if($overCount)<span>Qo'lda tuzatilgan: <b class="text-blue-700">{{ $overCount }}</b></span>@endif
                        </div>

                        @if($level['ai_error'] ?? null)
                            <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900 lg:col-span-12">
                                <b>AI moslashtirishda xato:</b> {{ $level['ai_error'] }}. Hozir qoida bo'yicha natija ishlatilmoqda — «Qayta moslashtirish»ni bosing.
                            </div>
                        @endif
                    </div>

                    {{-- Filtrlar --}}
                    @php
                        $counts = [
                            'review' => collect($res['rows'])->filter(fn ($r) => $r['farq'] || $r['disagree'] || $r['overridden'])->count(),
                            'farq' => $res['farq_count'],
                            'disagree' => $res['disagree'],
                            'over' => $overCount,
                            'all' => count($res['rows']),
                        ];
                        $filters = ['review' => 'Tekshirish kerak', 'farq' => 'Farq', 'disagree' => 'Qoida ≠ AI', 'over' => 'Qo\'lda tuzatilgan', 'all' => 'Hammasi'];
                    @endphp
                    <div class="flex flex-wrap items-center gap-2 border-y border-slate-200 bg-slate-50 px-5 py-2.5" role="tablist" aria-label="Qatorlar filtri">
                        @foreach($filters as $fk => $fl)
                            @continue($fk === 'over' && !$counts['over'])
                            @continue($fk === 'disagree' && !$hasAi)
                            <button type="button" role="tab" @click="filter = '{{ $fk }}'" :aria-selected="filter === '{{ $fk }}'"
                                    :class="filter === '{{ $fk }}' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold">
                                {{ $fl }}
                                <span :class="filter === '{{ $fk }}' ? 'bg-white/20' : 'bg-slate-100'" class="rounded-full px-1.5 text-[11px] tabular-nums">{{ $counts[$fk] }}</span>
                            </button>
                        @endforeach
                        <span class="ml-auto text-xs text-slate-600" x-show="filter === 'review'">Farqlar, qoida va AI kelishmagan hamda qo'lda tuzatilgan qatorlar</span>
                    </div>

                    <form method="POST" action="{{ route('admin.subject-differences.levels.mapping', [$check, $level['id']]) }}" @change="dirty++">
                        @csrf
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-white text-left text-xs font-semibold text-slate-600">
                                    <tr class="border-b border-slate-200">
                                        <th class="px-4 py-2.5">Bizning fan</th>
                                        <th class="px-3 py-2.5 text-right">Kredit</th>
                                        <th class="px-3 py-2.5">Transkriptdagi mos fan</th>
                                        <th class="px-3 py-2.5 text-center">Ball</th>
                                        <th class="px-3 py-2.5 text-center">Baho</th>
                                        <th class="px-3 py-2.5 text-right">K×B</th>
                                        <th class="px-3 py-2.5">Holat</th>
                                        <th class="px-3 py-2.5">Tuzatish</th>
                                        <th class="px-3 py-2.5 text-center"><span title="Hisobdan chiqarish (masalan, bir-ikki talabada uchragan ortiqcha yozuv)">Chiqarish</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $prevSem = null; @endphp
                                    @foreach($res['rows'] as $r)
                                        @php
                                            $rule = $r['rule_tr'] !== null ? ($trRows[$r['rule_tr']] ?? null) : null;
                                            $ai = $r['ai_tr'] !== null ? ($trRows[$r['ai_tr']] ?? null) : null;
                                            $sel = $r['overridden'] ? ($level['overrides'][$r['key']] === null ? 'none' : (string) $level['overrides'][$r['key']]) : 'auto';
                                            $rowBg = $r['excluded'] ? 'bg-slate-50 text-slate-500' : ($r['farq'] ? 'bg-rose-50/70' : ($r['disagree'] ? 'bg-amber-50/70' : 'bg-white'));
                                            $standard = ["Transkriptda yo'q — fanlar farqi", 'Tanlov fan (guruhdagi muqobil) — farq sifatida hisoblandi', 'Xodim hisobdan chiqargan'];
                                            $extra = array_values(array_filter($r['notes'], fn ($x) => !in_array($x, $standard, true) && !str_starts_with($x, "O'zlashtirilmagan")));
                                            $show = "filter === 'all'"
                                                . ($r['farq'] || $r['disagree'] || $r['overridden'] ? " || filter === 'review'" : '')
                                                . ($r['farq'] ? " || filter === 'farq'" : '')
                                                . ($r['disagree'] ? " || filter === 'disagree'" : '')
                                                . ($r['overridden'] ? " || filter === 'over'" : '');
                                            $semRows = collect($res['rows'])->where('semester', $r['semester']);
                                        @endphp
                                        @if($prevSem !== $r['semester'])
                                            @php $prevSem = $r['semester']; $semFarq = $semRows->where('farq', true)->where('excluded', false)->count(); @endphp
                                            <tr x-show="filter === 'all'" class="border-y border-slate-200 bg-slate-100/80">
                                                <td colspan="9" class="px-4 py-1.5 text-xs font-bold text-slate-700">
                                                    {{ $r['semester'] }}-semestr
                                                    <span class="ml-2 font-normal text-slate-600">{{ $semRows->count() }} fan · {{ $fmt($semRows->sum('credit')) }} kredit</span>
                                                    @if($semFarq)<span class="ml-2 rounded-full bg-rose-100 px-2 py-0.5 font-semibold text-rose-700">{{ $semFarq }} farq</span>@endif
                                                </td>
                                            </tr>
                                        @endif
                                        <tr x-show="{{ $show }}" class="{{ $rowBg }} border-b border-slate-100 align-top">
                                            <td class="px-4 py-2">
                                                <div class="font-medium text-slate-900 {{ $r['excluded'] ? 'line-through' : '' }}">{{ $r['name'] }}</div>
                                                <div class="mt-0.5 text-xs text-slate-600">
                                                    <span x-show="filter !== 'all'">{{ $r['semester'] }}-sem · </span>
                                                    @if($r['type'])<span class="{{ $isElective($r['type']) ? 'font-semibold text-violet-700' : '' }}">{{ $r['type'] }}</span>@endif
                                                    @if($isElective($r['type']) || $r['students'] < 10)<span title="Shu rejada bu fan bo'yicha baholangan talabalar soni"> · {{ $r['students'] }} talaba</span>@endif
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($r['credit']) }}</td>
                                            <td class="px-3 py-2">
                                                @if($r['tr_name'])
                                                    <div class="text-slate-900">{{ $r['tr_name'] }}</div>
                                                    <div class="mt-0.5 text-xs text-slate-600">{{ $r['tr_semester'] ? $r['tr_semester'] . '-sem' : '' }}{{ $r['tr_credit'] ? ' · ' . $fmt($r['tr_credit']) . ' kredit' : '' }}</div>
                                                @else
                                                    <div class="text-slate-500">Topilmadi</div>
                                                @endif
                                                @if($r['overridden'])
                                                    <span class="mt-1 inline-block rounded bg-blue-100 px-1.5 py-0.5 text-[11px] font-semibold text-blue-800">Qo'lda tanlangan</span>
                                                @elseif($r['disagree'])
                                                    <div class="mt-1 space-y-0.5 rounded-md border border-amber-300 bg-white px-2 py-1 text-xs">
                                                        <div><span class="font-semibold text-slate-700">Qoida:</span> {{ $rule ? $trLabel($rule) : 'topilmadi' }}@if($rule && $r['rule_score'] !== null) <span class="text-slate-600">({{ number_format($r['rule_score'] * 100) }}%)</span>@endif</div>
                                                        <div><span class="font-semibold text-violet-700">AI:</span> {{ $ai ? $trLabel($ai) : 'topilmadi' }}@if($r['ai_confidence'] !== null) <span class="text-slate-600">({{ number_format($r['ai_confidence'] * 100) }}%)</span>@endif</div>
                                                        @if($r['ai_reason'])<div class="text-slate-600">{{ $r['ai_reason'] }}</div>@endif
                                                    </div>
                                                @elseif($hasAi && $r['tr_name'])
                                                    <span class="mt-1 inline-flex items-center gap-1 text-[11px] text-emerald-700" title="{{ $r['ai_reason'] }}">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                        Qoida va AI bir xil
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center tabular-nums text-slate-700">{{ $fmt($r['tr_ball']) }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold {{ $r['grade'] < 3 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-900' }}">{{ $r['grade'] }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $fmt($r['product']) }}</td>
                                            <td class="px-3 py-2">
                                                <div class="flex flex-wrap gap-1">
                                                    @if($r['excluded'])
                                                        <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[11px] font-semibold text-slate-700">Chiqarilgan</span>
                                                    @elseif($r['farq'])
                                                        <span class="rounded bg-rose-600 px-1.5 py-0.5 text-[11px] font-semibold text-white" title="{{ $r['tr_name'] ? 'O\'zlashtirilmagan baho' : 'Transkriptda mos fan yo\'q' }}">Farq</span>
                                                        @if($isElective($r['type']))<span class="rounded bg-violet-100 px-1.5 py-0.5 text-[11px] font-semibold text-violet-800" title="Tanlov fan (guruhdagi muqobil) — farq sifatida hisoblandi">Tanlov</span>@endif
                                                    @else
                                                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-800">O'tdi</span>
                                                    @endif
                                                    @foreach($extra as $note)
                                                        <span class="cursor-help rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-900" title="{{ $note }}">⚠ {{ \Illuminate\Support\Str::limit($note, 28) }}</span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="px-3 py-2">
                                                {{-- Transkript qatorlari ro'yxati select ochilganda JS bilan to'ldiriladi (sahifa yengil bo'lsin) --}}
                                                <select name="map[{{ $r['key'] }}]" data-level="{{ $level['id'] }}" onfocus="sdFill(this)" onmousedown="sdFill(this)" aria-label="{{ $r['name'] }} uchun mos fan"
                                                        class="w-56 rounded-md py-1 text-xs {{ $r['overridden'] ? 'border-blue-500 ring-1 ring-blue-300' : 'border-slate-300' }}">
                                                    <option value="auto" @selected($sel === 'auto')>Avtomatik ({{ $hasAi ? 'AI' : 'qoida' }})</option>
                                                    <option value="none" @selected($sel === 'none')>— Mos fan yo'q (farq)</option>
                                                    @if(is_numeric($sel) && isset($trRows[(int) $sel]))
                                                        <option value="{{ $sel }}" selected>{{ $optLabel((int) $sel, $trRows[(int) $sel]) }}</option>
                                                    @endif
                                                </select>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <input type="checkbox" name="excluded[]" value="{{ $r['key'] }}" @checked($r['excluded']) class="h-4 w-4 rounded border-slate-400"
                                                       aria-label="{{ $r['name'] }} — hisobdan chiqarish">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div :class="dirty ? 'sticky bottom-0 z-10 border-blue-300 bg-blue-50 shadow-[0_-4px_12px_rgba(15,23,42,0.08)]' : 'border-slate-200 bg-slate-50'"
                             class="flex flex-wrap items-center justify-between gap-3 rounded-b-2xl border-t px-5 py-3 text-xs text-slate-600">
                            <span x-show="!dirty">Moslikni o'zgartirsangiz yoki qatorni hisobdan chiqarsangiz, GPA saqlangandan keyin qayta hisoblanadi.</span>
                            <span x-show="dirty" x-cloak class="font-semibold text-blue-900">Saqlanmagan o'zgarishlar bor — GPA hali eski.</span>
                            <button class="inline-flex h-9 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">Saqlash va qayta hisoblash</button>
                        </div>
                    </form>
                </div>
            @endforeach

            {{-- ───────────── Yangi bosqich ───────────── --}}
            @if($check->status !== 'error' && $rows)
                <form method="POST" action="{{ route('admin.subject-differences.levels.store', $check) }}"
                      class="rounded-2xl border-2 border-dashed {{ $suggestUpto ? 'border-rose-300' : 'border-blue-300' }} bg-white p-5 shadow-sm"
                      x-data="curriculumPicker('{{ route('admin.subject-differences.curricula') }}')" x-init="load()" @submit="busy = true">
                    @csrf
                    <h3 class="text-sm font-bold text-slate-800">{{ count($levels) ? (count($levels) + 1) . '-bosqich: ' . ($suggestUpto ? 'pastki kurs o\'quv rejasi bilan' : 'boshqa o\'quv reja bilan') : 'O\'quv reja bilan solishtirish' }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        @if($suggestUpto)
                            <b class="text-rose-600">Oxirgi bosqichda talaba o'tmadi.</b> Pastki kurs o'quv rejasini tanlang va {{ $suggestUpto }}-semestrgacha tekshiring.
                        @else
                            Talaba qabul qilinadigan kursdagi o'quv rejani tanlang. Masalan, 5-kursga qabul uchun 1–8-semestr fanlari.
                        @endif
                        Fanlar ro'yxati akkreditatsiyadan (shu rejada haqiqatda o'tilgan va baholangan fanlar) olinadi.
                    </p>

                    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-12">
                        <div class="lg:col-span-7">
                            <label class="block text-xs font-semibold text-slate-600">O'quv reja (HEMIS)</label>
                            <input type="text" x-model="q" @input.debounce.400ms="load()" placeholder="Qidirish: yo'nalish, reja nomi, o'quv yili…"
                                   class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            <select name="curriculum_id" required size="6" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <template x-for="c in list" :key="c.id">
                                    <option :value="c.id" x-text="label(c)"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500" x-show="loading">Yuklanmoqda…</p>
                            <p class="mt-1 text-[11px] text-slate-500" x-show="!loading && list.length === 0">Akkreditatsiya ma'lumoti bor o'quv reja topilmadi.</p>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600">Qaysi semestrgacha</label>
                            <select name="upto" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                @for($s = 1; $s <= 12; $s++)
                                    <option value="{{ $s }}" @selected($s === ($suggestUpto ?? 8))>1–{{ $s }}-semestr → {{ $s === 1 ? '1-kurs 2-sem' : (intdiv($s, 2) + 1) . '-kurs' }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="lg:col-span-3">
                            <label class="block text-xs font-semibold text-slate-600">Moslashtirish</label>
                            <label class="mt-2 flex items-center gap-2 text-sm">
                                <input type="hidden" name="use_ai" value="0">
                                <input type="checkbox" name="use_ai" value="1" {{ $aiConfigured ? 'checked' : 'disabled' }}>
                                Qoida + AI (Gemini)
                            </label>
                            <button type="submit" :disabled="busy"
                                    class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                                <span x-text="busy ? 'Solishtirilmoqda… (1 daqiqagacha)' : 'Solishtirish'">Solishtirish</span>
                            </button>
                        </div>
                    </div>
                </form>
            @endif

            {{-- ───────────── Ma'lumotlar ───────────── --}}
            <details class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-slate-800">Talaba ma'lumotlari, shkala va izoh</summary>
                <form method="POST" action="{{ route('admin.subject-differences.update', $check) }}" class="grid grid-cols-1 gap-3 border-t border-slate-200 p-4 md:grid-cols-2" x-data="{ mode: '{{ $scale->mode }}' }">
                    @csrf
                    <label class="text-xs font-semibold text-slate-600">Talaba F.I.Sh.
                        <input type="text" name="student_name" value="{{ $check->student_name }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Avvalgi OTM
                        <input type="text" name="source_institution" value="{{ $check->source_institution }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <div class="text-xs font-semibold text-slate-600">Baho shkalasi
                        <div class="mt-1 flex flex-wrap items-center gap-3 font-normal">
                            <label><input type="radio" name="scale_mode" value="ball" x-model="mode"> balldan</label>
                            <label><input type="radio" name="scale_mode" value="grade" x-model="mode"> yozilgan baho</label>
                            <template x-if="mode === 'ball'"><span class="flex gap-1">
                                <input type="number" step="0.01" name="scale_five" value="{{ $scale->five }}" class="w-16 rounded border-slate-300 px-1 py-0.5 text-xs" title="5 baho ≥">
                                <input type="number" step="0.01" name="scale_four" value="{{ $scale->four }}" class="w-16 rounded border-slate-300 px-1 py-0.5 text-xs" title="4 baho ≥">
                                <input type="number" step="0.01" name="scale_three" value="{{ $scale->three }}" class="w-16 rounded border-slate-300 px-1 py-0.5 text-xs" title="3 baho ≥">
                            </span></template>
                        </div>
                    </div>
                    <label class="text-xs font-semibold text-slate-600 md:col-span-2">Izoh
                        <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ $check->notes }}</textarea>
                    </label>
                    <div class="flex items-center justify-between md:col-span-2">
                        <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">Saqlash</button>
                        <span class="text-[11px] text-slate-500">Yaratgan: {{ $check->created_by_name }} · {{ $check->created_at?->format('d.m.Y H:i') }}</span>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.subject-differences.destroy', $check) }}" onsubmit="return confirm('Tekshiruv va yuklangan fayllar butunlay o\'chirilsinmi?')" class="border-t border-slate-200 px-4 py-3">
                    @csrf @method('DELETE')
                    <button class="text-xs font-semibold text-rose-600 hover:underline">Tekshiruvni o'chirish</button>
                </form>
            </details>
        </div>

        {{-- Transkript qatorini tuzatish --}}
        <div x-show="edit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @keydown.escape.window="edit = null">
            <form method="POST" action="{{ route('admin.subject-differences.row', $check) }}" class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl" @click.outside="edit = null">
                @csrf
                <template x-if="edit">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Transkript qatorini tuzatish</h3>
                        <p class="mt-0.5 text-xs text-slate-500">AI yoki matndan noto'g'ri o'qilgan qiymatni PDF bilan solishtirib tuzating.</p>
                        <input type="hidden" name="source" :value="edit.source">
                        <input type="hidden" name="index" :value="edit.index">
                        <label class="mt-3 block text-xs font-semibold text-slate-600">Fan nomi
                            <input type="text" name="name" x-model="edit.name" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        </label>
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <label class="text-xs font-semibold text-slate-600">Semestr<input type="number" name="semester" x-model="edit.semester" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                            <label class="text-xs font-semibold text-slate-600">Ball<input type="number" step="0.01" name="ball" x-model="edit.ball" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                            <label class="text-xs font-semibold text-slate-600">Baho<input type="number" name="grade" x-model="edit.grade" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="edit = null" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Bekor qilish</button>
                            <button class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white">Saqlash</button>
                        </div>
                    </div>
                </template>
            </form>
        </div>
    </div>

    <script>
        function sdFill(sel) {
            if (sel.dataset.filled) return;
            sel.dataset.filled = '1';
            const current = sel.value;
            (window.sdOpts[sel.dataset.level] || []).forEach(([i, label]) => {
                if (String(i) === current) return;
                const o = document.createElement('option');
                o.value = i; o.textContent = label;
                sel.appendChild(o);
            });
            sel.value = current;
        }

        function curriculumPicker(url) {
            return {
                q: '', list: [], loading: false, busy: false,
                async load() {
                    this.loading = true;
                    try {
                        const r = await fetch(url + '?q=' + encodeURIComponent(this.q), { headers: { 'Accept': 'application/json' } });
                        this.list = r.ok ? await r.json() : [];
                    } finally { this.loading = false; }
                },
                label(c) {
                    return [c.name, c.specialty_name && c.specialty_name !== c.name ? c.specialty_name : null, c.education_year_name, c.education_form_name]
                        .filter(Boolean).join(' · ');
                },
            };
        }
    </script>
</x-app-layout>
