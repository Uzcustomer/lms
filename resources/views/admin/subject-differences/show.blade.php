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

            {{-- ───────────── Natija (bosqichlar) ───────────── --}}
            @foreach($levels as $n => $item)
                @php
                    $level = $item['level'];
                    $res = $item['result'];
                    $dec = $res['decision'];
                    $trRows = $res['transcript'];
                    $hasAi = $res['ai_gpa'] !== null;
                    $isAnswer = $n === $answerIndex;
                    $isElective = fn ($t) => \App\Services\SubjectDifference\DifferenceCalculator::isElective($t);
                    $optLabel = fn ($ti, $tr) => \Illuminate\Support\Str::limit($trLabel($tr), 60) . ' · ' . $fmt($tr['ball'] ?? null) . '/' . ($tr['grade'] ?? '—');
                    $optList = collect($trRows)->map(fn ($tr, $ti) => [$ti, $optLabel($ti, $tr)])->values();
                    $farqRows = collect($res['rows'])->filter(fn ($r) => $r['farq'] && !$r['excluded'])->values();
                    $title = (int) $level['upto'] <= 1 ? '1-kurs (2-semestrdan)' : $dec['kurs'] . '-kursga qabul';
                    // Moslik foizi: qo'lda tanlangan bo'lsa "qo'lda", aks holda yakuniy moslikni bergan usulning ishonchi
                    $matchPct = function ($r) use ($hasAi) {
                        if ($r['overridden']) {
                            return ['text' => 'qo\'lda', 'title' => 'Xodim qo\'lda tanlagan'];
                        }
                        if ($r['tr'] === null) {
                            return ['text' => '—', 'title' => 'Mos fan topilmadi'];
                        }
                        $v = $hasAi ? $r['ai_confidence'] : $r['rule_score'];
                        return ['text' => $v !== null ? round($v * 100) . '%' : '—', 'title' => ($hasAi ? 'AI ishonchi' : 'Nom o\'xshashligi') . ($r['ai_reason'] ? ': ' . $r['ai_reason'] : '')];
                    };
                @endphp
                <script>window.sdOpts = window.sdOpts || {}; window.sdOpts[{{ \Illuminate\Support\Js::from($level['id']) }}] = {{ \Illuminate\Support\Js::from($optList) }};</script>

                <details class="group rounded-xl border border-slate-300 bg-white" id="level-{{ $level['id'] }}" @if($isAnswer) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 px-4 py-3 [&::-webkit-details-marker]:hidden">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-slate-500 transition group-open:rotate-90" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            <h3 class="text-base font-bold text-slate-900">{{ $title }}</h3>
                            <span class="text-sm text-slate-600">· {{ $level['curriculum_name'] }} · 1–{{ $level['upto'] }}-semestr</span>
                        </div>
                        <span class="rounded-md px-2 py-1 text-sm font-semibold {{ $dec['passed'] ? 'bg-emerald-100 text-emerald-900' : 'bg-rose-100 text-rose-900' }}">
                            GPA {{ number_format($res['gpa'], 2) }} · {{ $dec['passed'] ? 'o\'tdi' : 'o\'tmadi' }}
                        </span>
                    </summary>

                    <form method="POST" action="{{ route('admin.subject-differences.levels.mapping', [$check, $level['id']]) }}"
                          x-data="{ edit: false, dirty: 0 }" @change="dirty++" class="border-t border-slate-300">
                        @csrf
                        <div class="flex flex-wrap items-center justify-end gap-2 px-4 py-2">
                            <button type="button" @click="edit = !edit" class="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                                    x-text="edit ? 'Tahrirlashni yopish' : 'Moslikni tahrirlash'">Moslikni tahrirlash</button>
                            <a href="{{ route('admin.subject-differences.levels.excel', [$check, $level['id']]) }}"
                               class="inline-flex h-8 items-center rounded-md bg-emerald-600 px-3 text-sm font-semibold text-white hover:bg-emerald-700">Excel</a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full border-collapse text-sm">
                                <thead>
                                    <tr class="bg-slate-100 text-left text-slate-800">
                                        <th class="border border-slate-300 px-3 py-2 font-semibold">№</th>
                                        <th class="border border-slate-300 px-3 py-2 font-semibold">O'quv reja fani</th>
                                        <th class="border border-slate-300 px-3 py-2 text-center font-semibold">Kredit</th>
                                        <th class="border border-slate-300 px-3 py-2 font-semibold">Transkriptdagi fan</th>
                                        <th class="border border-slate-300 px-3 py-2 text-center font-semibold">Moslik</th>
                                        <th class="border border-slate-300 px-3 py-2 text-center font-semibold">Baho</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $prevSem = null; $no = 0; @endphp
                                    @foreach($res['rows'] as $r)
                                        @php
                                            $no++;
                                            $sel = $r['excluded'] ? 'exclude' : ($r['overridden'] ? ($level['overrides'][$r['key']] === null ? 'none' : (string) $level['overrides'][$r['key']]) : 'auto');
                                            $pct = $matchPct($r);
                                            $rowBg = $r['excluded'] ? 'bg-slate-50 text-slate-500' : ($r['farq'] ? 'bg-rose-50' : '');
                                        @endphp
                                        @if($prevSem !== $r['semester'])
                                            @php $prevSem = $r['semester']; @endphp
                                            <tr><td colspan="6" class="border border-slate-300 bg-slate-50 px-3 py-1.5 text-center text-sm font-bold text-slate-800">{{ $r['semester'] }}-semestr</td></tr>
                                        @endif
                                        <tr class="{{ $rowBg }}">
                                            <td class="border border-slate-300 px-3 py-1.5 text-center tabular-nums text-slate-600">{{ $no }}</td>
                                            <td class="border border-slate-300 px-3 py-1.5 {{ $r['excluded'] ? 'line-through' : 'text-slate-900' }}">
                                                {{ $r['name'] }}
                                                @if($isElective($r['type']))<span class="ml-1 text-xs text-violet-700">(tanlov)</span>@endif
                                            </td>
                                            <td class="border border-slate-300 px-3 py-1.5 text-center tabular-nums">{{ $fmt($r['credit']) }}</td>
                                            <td class="border border-slate-300 px-3 py-1.5">
                                                <div x-show="!edit">
                                                    @if($r['excluded'])
                                                        <span class="text-slate-500">Hisobga olinmagan</span>
                                                    @elseif($r['tr_name'])
                                                        {{ $r['tr_name'] }}
                                                        <span class="text-xs text-slate-600">({{ $r['tr_semester'] ? $r['tr_semester'] . '-sem, ' : '' }}{{ $fmt($r['tr_ball']) }} ball)</span>
                                                    @else
                                                        <span class="font-semibold text-rose-700">Yo'q — fanlar farqi</span>
                                                    @endif
                                                </div>
                                                <select x-show="edit" x-cloak name="map[{{ $r['key'] }}]" data-level="{{ $level['id'] }}" onfocus="sdFill(this)" onmousedown="sdFill(this)" aria-label="{{ $r['name'] }} uchun mos fan"
                                                        class="w-full min-w-[16rem] rounded border-slate-300 py-1 text-sm">
                                                    <option value="auto" @selected($sel === 'auto')>{{ $r['tr_name'] && !$r['overridden'] ? \Illuminate\Support\Str::limit($r['tr_name'], 55) . ' · ' . $fmt($r['tr_ball']) . ' ball' : 'Avtomatik: topilmadi' }}</option>
                                                    <option value="none" @selected($sel === 'none')>— Mos fan yo'q (farq)</option>
                                                    <option value="exclude" @selected($sel === 'exclude')>— Hisobga olinmasin</option>
                                                    @if(is_numeric($sel) && isset($trRows[(int) $sel]))
                                                        <option value="{{ $sel }}" selected>{{ $optLabel((int) $sel, $trRows[(int) $sel]) }}</option>
                                                    @endif
                                                </select>
                                            </td>
                                            <td class="border border-slate-300 px-3 py-1.5 text-center tabular-nums" title="{{ $pct['title'] }}">
                                                {{ $pct['text'] }}
                                                @if($r['disagree'] && !$r['overridden'])<span class="cursor-help text-amber-700" title="Nom bo'yicha boshqa natija chiqdi — tekshiring">⚠</span>@endif
                                            </td>
                                            <td class="border border-slate-300 px-3 py-1.5 text-center font-bold tabular-nums {{ $r['excluded'] ? '' : ($r['grade'] < 3 ? 'text-rose-700' : 'text-slate-900') }}">{{ $r['excluded'] ? '—' : $r['grade'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-slate-100 font-semibold text-slate-900">
                                        <td colspan="2" class="border border-slate-300 px-3 py-2 text-right">Jami kredit</td>
                                        <td class="border border-slate-300 px-3 py-2 text-center tabular-nums">{{ $fmt($res['total_credit']) }}</td>
                                        <td colspan="2" class="border border-slate-300 px-3 py-2 text-right">GPA ({{ $fmt($res['total_product']) }} / {{ $fmt($res['total_credit']) }})</td>
                                        <td class="border border-slate-300 px-3 py-2 text-center text-base tabular-nums {{ $dec['passed'] ? 'text-emerald-800' : 'text-rose-700' }}">{{ number_format($res['gpa'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- Yakun: GPA va fanlar farqi --}}
                        <div class="grid grid-cols-1 gap-4 px-4 py-4 md:grid-cols-2">
                            <div class="rounded-lg border p-4 {{ $dec['passed'] ? 'border-emerald-300 bg-emerald-50' : 'border-rose-300 bg-rose-50' }}">
                                <div class="text-sm text-slate-700">GPA</div>
                                <div class="text-3xl font-bold tabular-nums {{ $dec['passed'] ? 'text-emerald-900' : 'text-rose-800' }}">{{ number_format($res['gpa'], 2) }}</div>
                                <div class="mt-1 text-sm font-semibold {{ $dec['passed'] ? 'text-emerald-900' : 'text-rose-900' }}">{{ $dec['text'] }}</div>
                                @if(!$dec['passed'] && $n === count($levels) - 1)
                                    <a href="#new-level" class="mt-2 inline-block text-sm font-semibold text-rose-800 underline">Pastki kurs bilan tekshirish ↓</a>
                                @endif
                            </div>
                            <div class="rounded-lg border border-slate-300 p-4">
                                <div class="text-sm text-slate-700">Fanlar farqi</div>
                                <div class="text-3xl font-bold tabular-nums text-slate-900">{{ $farqRows->count() }} <span class="text-base font-semibold text-slate-700">fan, {{ $fmt($farqRows->sum('credit')) }} kredit</span></div>
                                @if($farqRows->isNotEmpty())
                                    <ol class="mt-2 list-decimal space-y-0.5 pl-5 text-sm text-slate-800">
                                        @foreach($farqRows as $r)
                                            <li>{{ $r['name'] }} <span class="text-slate-600">— {{ $r['semester'] }}-sem, {{ $fmt($r['credit']) }} kredit{{ $isElective($r['type']) ? ', tanlov' : '' }}</span></li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                        </div>

                        <div x-show="edit || dirty" x-cloak class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-b-xl border-t border-blue-300 bg-blue-50 px-4 py-3 text-sm">
                            <span class="text-blue-900" x-text="dirty ? 'Saqlanmagan o\'zgarishlar bor' : 'Mos fanni tanlang va saqlang — GPA qayta hisoblanadi'"></span>
                            <div class="flex items-center gap-3">
                                <button type="submit" form="rematch-{{ $level['id'] }}" class="text-sm font-semibold text-slate-700 underline">Qayta moslashtirish</button>
                                <button type="submit" form="delete-{{ $level['id'] }}" class="text-sm font-semibold text-rose-700 underline">O'chirish</button>
                                <button class="inline-flex h-9 items-center rounded-md bg-blue-600 px-4 font-semibold text-white hover:bg-blue-700">Saqlash</button>
                            </div>
                        </div>
                    </form>
                    <form id="rematch-{{ $level['id'] }}" method="POST" action="{{ route('admin.subject-differences.levels.rematch', [$check, $level['id']]) }}" class="hidden">@csrf<input type="hidden" name="use_ai" value="1"></form>
                    <form id="delete-{{ $level['id'] }}" method="POST" action="{{ route('admin.subject-differences.levels.destroy', [$check, $level['id']]) }}" class="hidden" onsubmit="return confirm('Bu tekshiruv o\'chirilsinmi?')">@csrf @method('DELETE')</form>
                </details>
            @endforeach

            {{-- ───────────── Yangi bosqich ───────────── --}}
            @if($check->status !== 'error' && $rows)
                @php $needNew = !count($levels) || $suggestUpto; @endphp
                <details id="new-level" class="rounded-2xl border bg-white shadow-sm {{ $needNew ? 'border-blue-300' : 'border-slate-200' }}" @if($needNew) open @endif>
                    <summary class="cursor-pointer list-none px-5 py-4 [&::-webkit-details-marker]:hidden">
                        <span class="text-base font-bold text-slate-900">
                            @if(!count($levels)) O'quv reja bilan solishtirish
                            @elseif($suggestUpto) Pastki kurs bilan tekshirish
                            @else <span class="text-sm font-semibold text-blue-700">+ Boshqa o'quv reja bilan tekshirish</span>
                            @endif
                        </span>
                        @if($needNew)
                            <span class="mt-1 block text-sm text-slate-600">
                                @if($suggestUpto)
                                    Talaba yuqoridagi kursga o'tmadi. Pastki kurs o'quv rejasini tanlang — {{ $suggestUpto }}-semestrgacha tekshiriladi.
                                @else
                                    Talaba qabul qilinadigan kursdagi o'quv rejani tanlang. Masalan, 5-kursga qabul uchun 1–8-semestr fanlari tekshiriladi.
                                @endif
                            </span>
                        @endif
                    </summary>
                    <form method="POST" action="{{ route('admin.subject-differences.levels.store', $check) }}" class="border-t border-slate-200 px-5 py-4"
                          x-data="curriculumPicker('{{ route('admin.subject-differences.curricula') }}')" x-init="load()" @submit="busy = true">
                        @csrf
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                            <div class="lg:col-span-7">
                                <label class="block text-sm font-semibold text-slate-800" for="curriculum-search">1. O'quv reja</label>
                                <input id="curriculum-search" type="text" x-model="q" @input.debounce.400ms="load()" placeholder="Qidirish: yo'nalish, o'quv yili…"
                                       class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <select name="curriculum_id" required size="5" aria-label="O'quv reja" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                    <template x-for="c in list" :key="c.id">
                                        <option :value="c.id" x-text="label(c)"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-slate-600" x-show="loading">Yuklanmoqda…</p>
                                <p class="mt-1 text-xs text-slate-600" x-show="!loading && list.length === 0">O'quv reja topilmadi.</p>
                            </div>
                            <div class="lg:col-span-5">
                                <label class="block text-sm font-semibold text-slate-800" for="upto">2. Qaysi kursga qabul?</label>
                                <select id="upto" name="upto" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                    @for($s = 1; $s <= 12; $s++)
                                        <option value="{{ $s }}" @selected($s === ($suggestUpto ?? 8))>{{ $s === 1 ? '1-kurs (2-semestrdan) — 1-semestr fanlari' : (intdiv($s, 2) + 1) . '-kurs — 1–' . $s . '-semestr fanlari' }}</option>
                                    @endfor
                                </select>
                                <input type="hidden" name="use_ai" value="{{ $aiConfigured ? 1 : 0 }}">
                                <button type="submit" :disabled="busy"
                                        class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                                    <span x-text="busy ? 'Tekshirilmoqda… (1 daqiqagacha)' : 'Tekshirish'">Tekshirish</span>
                                </button>
                                <p class="mt-2 text-xs text-slate-600">Fanlar shu rejada haqiqatda o'tilgan fanlardan (akkreditatsiya) olinadi.{{ $aiConfigured ? '' : ' AI kaliti sozlanmagan — faqat nom bo\'yicha moslashtiriladi.' }}</p>
                            </div>
                        </div>
                    </form>
                </details>
            @endif

            {{-- ───────────── Transkript ───────────── --}}
<details class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 bg-slate-50 px-4 py-3 [&::-webkit-details-marker]:hidden">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-slate-500 transition group-open:rotate-90" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
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
