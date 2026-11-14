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
                        <h3 class="inline text-sm font-bold text-slate-800">1. Transkriptdan o'qilgan fanlar</h3>
                        <span class="ml-2 text-xs text-slate-500">{{ count($rows) }} qator · asosiy manba: {{ $sourceLabels[$source] ?? $source }}</span>
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
                                            <td class="px-3 py-1.5 text-slate-400">{{ $i + 1 }}</td>
                                            <td class="px-3 py-1.5">{{ $r['semester'] ?? '—' }}</td>
                                            <td class="px-3 py-1.5 text-slate-800">{{ $r['name'] }} @if(!empty($r['edited']))<span class="text-[10px] text-blue-600">(tuzatilgan)</span>@endif</td>
                                            <td class="px-3 py-1.5 text-right">{{ $r['hours'] ?? '—' }} / {{ $fmt($r['credit'] ?? null) }}</td>
                                            <td class="px-3 py-1.5 text-right">{{ $fmt($r['ball'] ?? null) }} / {{ $r['grade'] ?? '—' }}@if(!empty($r['grade_text'])) <span class="text-slate-400">({{ $r['grade_text'] }})</span>@endif</td>
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
                    $optLabel = fn ($ti, $tr) => \Illuminate\Support\Str::limit($trLabel($tr), 60) . ' · ' . $fmt($tr['ball'] ?? null) . '/' . ($tr['grade'] ?? '—');
                    $optList = collect($trRows)->map(fn ($tr, $ti) => [$ti, $optLabel($ti, $tr)])->values();
                @endphp
                <script>window.sdOpts = window.sdOpts || {}; window.sdOpts[{{ \Illuminate\Support\Js::from($level['id']) }}] = {{ \Illuminate\Support\Js::from($optList) }};</script>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" id="level-{{ $level['id'] }}">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">{{ $n + 2 }}. {{ $level['curriculum_name'] }} · 1–{{ $level['upto'] }}-semestr fanlari</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ count($level['plan']) }} ta fan (akkreditatsiya) · transkript manbasi: {{ $sourceLabels[$level['source'] ?? 'ai'] ?? '' }}
                                @if($level['ai_model'] ?? null) · AI: {{ $level['ai_model'] }} @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <a href="{{ route('admin.subject-differences.levels.excel', [$check, $level['id']]) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 font-semibold text-white hover:bg-emerald-700">Excel</a>
                            <form method="POST" action="{{ route('admin.subject-differences.levels.rematch', [$check, $level['id']]) }}" x-data="{busy:false}" @submit="busy=true">
                                @csrf
                                <input type="hidden" name="use_ai" value="1">
                                <button :disabled="busy" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60" x-text="busy ? 'Moslashtirilmoqda…' : 'Qayta moslashtirish'">Qayta moslashtirish</button>
                            </form>
                            <form method="POST" action="{{ route('admin.subject-differences.levels.destroy', [$check, $level['id']]) }}" onsubmit="return confirm('Bosqich o\'chirilsinmi?')">
                                @csrf @method('DELETE')
                                <button class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 font-semibold text-rose-600 hover:bg-rose-50">O'chirish</button>
                            </form>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 p-4 md:grid-cols-3 xl:grid-cols-6">
                        <div class="rounded-xl border p-3 {{ $dec['passed'] ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }} col-span-2">
                            <div class="text-[11px] font-semibold {{ $dec['passed'] ? 'text-emerald-700' : 'text-rose-700' }}">Xulosa ({{ $dec['rule'] }})</div>
                            <div class="mt-1 text-sm font-bold {{ $dec['passed'] ? 'text-emerald-900' : 'text-rose-900' }}">{{ $dec['text'] }}</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <div class="text-[11px] font-semibold text-slate-500">Yakuniy GPA</div>
                            <div class="mt-1 text-2xl font-bold {{ $res['gpa'] >= 2.4 ? 'text-slate-800' : 'text-rose-600' }}">{{ number_format($res['gpa'], 3) }}</div>
                            <div class="text-[11px] text-slate-500">{{ $fmt($res['total_product']) }} / {{ $fmt($res['total_credit']) }} kredit</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <div class="text-[11px] font-semibold text-slate-500">Qoida bo'yicha GPA</div>
                            <div class="mt-1 text-xl font-bold text-slate-700">{{ number_format($res['rule_gpa'], 3) }}</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <div class="text-[11px] font-semibold text-slate-500">AI bo'yicha GPA</div>
                            <div class="mt-1 text-xl font-bold text-violet-700">{{ $res['ai_gpa'] !== null ? number_format($res['ai_gpa'], 3) : '—' }}</div>
                            @if($level['ai_error'] ?? null)<div class="text-[11px] text-rose-600" title="{{ $level['ai_error'] }}">AI xatosi</div>@endif
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3">
                            <div class="text-[11px] font-semibold text-slate-500">Fanlar farqi</div>
                            <div class="mt-1 text-xl font-bold text-rose-600">{{ $res['farq_count'] }} <span class="text-sm font-semibold text-slate-500">fan</span></div>
                            <div class="text-[11px] text-slate-500">{{ $fmt($res['farq_credit']) }} kredit · qoida≠AI: {{ $res['disagree'] }}</div>
                        </div>
                    </div>
                    @if($level['ai_error'] ?? null)
                        <div class="mx-4 mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">AI moslashtirishda xato: {{ $level['ai_error'] }}. Hozir qoida bo'yicha natija ishlatilmoqda.</div>
                    @endif

                    <form method="POST" action="{{ route('admin.subject-differences.levels.mapping', [$check, $level['id']]) }}">
                        @csrf
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="bg-slate-50 text-left font-semibold text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Sem</th>
                                        <th class="px-3 py-2">Bizning fan (akkreditatsiya)</th>
                                        <th class="px-3 py-2 text-right">Kredit</th>
                                        <th class="px-3 py-2">Qoida bo'yicha</th>
                                        <th class="px-3 py-2">AI bo'yicha</th>
                                        <th class="px-3 py-2">Yakuniy moslik</th>
                                        <th class="px-3 py-2 text-center">Ball</th>
                                        <th class="px-3 py-2 text-center">Baho</th>
                                        <th class="px-3 py-2 text-right">K×B</th>
                                        <th class="px-3 py-2">Izoh</th>
                                        <th class="px-3 py-2 text-center" title="Hisobga olinmasin">✕</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($res['rows'] as $r)
                                        @php
                                            $rule = $r['rule_tr'] !== null ? ($trRows[$r['rule_tr']] ?? null) : null;
                                            $ai = $r['ai_tr'] !== null ? ($trRows[$r['ai_tr']] ?? null) : null;
                                            $sel = $r['overridden'] ? ($level['overrides'][$r['key']] === null ? 'none' : (string) $level['overrides'][$r['key']]) : 'auto';
                                            $bg = $r['excluded'] ? 'bg-slate-100 text-slate-400' : ($r['farq'] ? 'bg-rose-50' : ($r['disagree'] ? 'bg-amber-50' : ''));
                                        @endphp
                                        <tr class="{{ $bg }} align-top">
                                            <td class="px-3 py-1.5">{{ $r['semester'] }}</td>
                                            <td class="px-3 py-1.5">
                                                <div class="font-medium text-slate-800">{{ $r['name'] }}</div>
                                                <div class="text-[10px] text-slate-500">
                                                    @if($r['type'])<span class="{{ \App\Services\SubjectDifference\DifferenceCalculator::isElective($r['type']) ? 'text-violet-600' : '' }}">{{ $r['type'] }}</span> · @endif
                                                    {{ $r['students'] }} talaba
                                                </div>
                                            </td>
                                            <td class="px-3 py-1.5 text-right">{{ $fmt($r['credit']) }}</td>
                                            <td class="px-3 py-1.5 {{ $rule ? '' : 'text-slate-400' }}">
                                                {{ $rule ? $trLabel($rule) : 'topilmadi' }}
                                                @if($rule && $r['rule_score'] !== null)<span class="text-[10px] text-slate-400">({{ number_format($r['rule_score'] * 100) }}%)</span>@endif
                                            </td>
                                            <td class="px-3 py-1.5 {{ $ai ? '' : 'text-slate-400' }}" title="{{ $r['ai_reason'] }}">
                                                @if($r['ai_tr'] === null && $res['ai_gpa'] === null) — @else
                                                    {{ $ai ? $trLabel($ai) : 'yo\'q' }}
                                                    @if($r['ai_confidence'] !== null)<span class="text-[10px] text-slate-400">({{ number_format($r['ai_confidence'] * 100) }}%)</span>@endif
                                                    @if($r['ai_reason'])<div class="text-[10px] text-slate-500">{{ \Illuminate\Support\Str::limit($r['ai_reason'], 90) }}</div>@endif
                                                @endif
                                            </td>
                                            <td class="px-3 py-1.5">
                                                {{-- Transkript qatorlari ro'yxati select ochilganda JS bilan to'ldiriladi (sahifa yengil bo'lsin) --}}
                                                <select name="map[{{ $r['key'] }}]" data-level="{{ $level['id'] }}" onfocus="sdFill(this)" onmousedown="sdFill(this)"
                                                        class="w-64 rounded-md border-slate-300 py-1 text-xs {{ $r['overridden'] ? 'border-blue-500 ring-1 ring-blue-300' : '' }}">
                                                    <option value="auto" @selected($sel === 'auto')>Avtomatik ({{ $res['ai_gpa'] !== null ? 'AI' : 'qoida' }})</option>
                                                    <option value="none" @selected($sel === 'none')>— Mos fan yo'q (farq)</option>
                                                    @if(is_numeric($sel) && isset($trRows[(int) $sel]))
                                                        <option value="{{ $sel }}" selected>{{ $optLabel((int) $sel, $trRows[(int) $sel]) }}</option>
                                                    @endif
                                                </select>
                                            </td>
                                            <td class="px-3 py-1.5 text-center">{{ $fmt($r['tr_ball']) }}</td>
                                            <td class="px-3 py-1.5 text-center font-bold {{ $r['grade'] < 3 ? 'text-rose-600' : 'text-slate-800' }}">{{ $r['grade'] }}</td>
                                            <td class="px-3 py-1.5 text-right">{{ $fmt($r['product']) }}</td>
                                            <td class="px-3 py-1.5 text-[11px] text-slate-600">{{ implode('; ', $r['notes']) }}</td>
                                            <td class="px-3 py-1.5 text-center">
                                                <input type="checkbox" name="excluded[]" value="{{ $r['key'] }}" @checked($r['excluded']) title="Hisobga olinmasin (masalan, bitta-ikki talabada uchragan ortiqcha yozuv)">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                            <span><span class="inline-block h-3 w-3 rounded bg-rose-100 align-middle"></span> fanlar farqi · <span class="inline-block h-3 w-3 rounded bg-amber-100 align-middle"></span> qoida va AI har xil topgan — tekshiring</span>
                            <button class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Tuzatishlarni saqlash va qayta hisoblash</button>
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
                    <h3 class="text-sm font-bold text-slate-800">{{ count($levels) + 2 }}. O'quv reja bilan solishtirish</h3>
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
