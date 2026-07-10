<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish - Test markazi") }}
        </h2>
    </x-slot>

    @include('partials._journal_table_styles')
    <style>
        /* Natija hali yo'q (baho qo'yilishi kerak, lekin hali yo'q) */
        .rtm-await {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:26px; height:22px; border-radius:6px;
            background:#f8fafc; border:1px dashed #cbd5e1; color:#94a3b8;
            font-size:13px; line-height:1;
        }
        /* Bu nazorat bu fanga umuman qo'yilmaydi */
        .rtm-na {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:26px; height:22px; border-radius:6px;
            background:transparent; color:#cbd5e1; font-size:13px; line-height:1;
        }
        /* Ustunlar bo'yicha filtr qatori */
        .rtm-filter-row th.rtm-fcell {
            padding:4px 6px !important; background:#f1f5f9; border-bottom:1px solid #e2e8f0;
        }
        .rtm-finput {
            width:100%; min-width:64px; box-sizing:border-box;
            font-size:11px; font-weight:500; padding:4px 6px;
            border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0f172a; outline:none;
        }
        .rtm-finput:focus { border-color:#2563eb; box-shadow:0 0 0 2px rgba(37,99,235,.15); }
        .rtm-fnum { min-width:52px; text-align:center; }
        .rtm-fclear {
            width:22px; height:22px; border:1px solid #cbd5e1; border-radius:6px;
            background:#fff; color:#94a3b8; font-size:11px; cursor:pointer; line-height:1;
        }
        .rtm-fclear:hover { background:#fee2e2; color:#dc2626; border-color:#fecaca; }
    </style>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Yuqori filtrlar — JN hisoboti uslubida (Ta'lim turi → Fakultet → Yo'nalish → Kurs → Semestr → Guruh + Fan) --}}
        @include('partials._retake_filters', [
            'formAction' => route('admin.retake-test-markazi.index'),
            'educationTypes' => $educationTypes ?? collect(),
            'subjects' => $subjects ?? collect(),
            'hiddenFilters' => ['full_name'],
            'extraQueryFields' => [
                'tab' => $activeTab,
                'student_search' => $studentSearch,
                'sent_status' => $sentStatus ?? '',
            ],
        ])

        <div class="mb-4 flex items-center gap-2 border-b border-gray-200 bg-white rounded-t-xl px-4 pt-3 shadow-sm mt-4">
            <a href="{{ route('admin.retake-test-markazi.index', array_merge(request()->except(['tab','page','groups_page','students_page']), ['tab' => 'groups'])) }}"
               class="px-5 py-3 text-sm font-semibold border-b-2 rounded-t-lg transition {{ $activeTab === 'groups' ? 'border-blue-600 text-blue-700 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">
                {{ __("Jami guruhlar") }}
            </a>
            <a href="{{ route('admin.retake-test-markazi.index', array_merge(request()->except(['tab','page','groups_page','students_page']), ['tab' => 'students'])) }}"
               class="px-5 py-3 text-sm font-semibold border-b-2 rounded-t-lg transition {{ $activeTab === 'students' ? 'border-blue-600 text-blue-700 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">
                {{ __("Testga yuborilgan talabalar") }}
            </a>
        </div>

        @php
            $atypeBadge = [
                'oske' => ['label' => 'OSKE', 'cls' => 'badge-blue'],
                'test' => ['label' => 'TEST', 'cls' => 'badge-green'],
                'oske_test' => ['label' => 'OSKE + TEST', 'cls' => 'badge-purple'],
                'sinov_fan' => ['label' => 'Sinov', 'cls' => 'badge-teal'],
                'sinov' => ['label' => 'Sinov', 'cls' => 'badge-teal'],
            ];
            $atype = fn ($t) => $atypeBadge[$t] ?? ['label' => $t ?: '—', 'cls' => 'badge-gray'];
            $fmt = fn ($v) => $v !== null ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : null;
            // Katak: nazorat faol bo'lmasa ✕, faol-u baho yo'q bo'lsa kutilmoqda (…),
            // baho bor bo'lsa rangli badge.
            $scoreCell = function ($active, $value, $cls = 'badge-blue', $date = null) use ($fmt) {
                if (!$active) return '<span class="rtm-na" title="Bu fanda bu nazorat qo\'yilmaydi">✕</span>';
                $f = $fmt($value);
                if ($f === null) {
                    return '<span class="rtm-await" title="Natija hali yo\'q">…</span>';
                }
                $title = $date
                    ? ' title="Baho qo\'yilgan sana: ' . e($date->format('d.m.Y H:i')) . '" style="cursor:help;"'
                    : '';
                return '<span class="badge ' . $cls . '"' . $title . '>' . $f . '</span>';
            };
            // Yakuniy natija — vedomost tekshirish logikasi bo'yicha hisoblangan holat.
            // $attempts — talaba shu fandan necha marta test topshirgani; faqat
            // qayta topshirgan (>=2) talabada "(N)" ko'rsatiladi.
            $finalCell = function ($res, $attempts = 1) {
                $suffix = $attempts >= 2 ? ' (' . $attempts . ')' : '';
                if (!$res) return '<span class="rtm-await" title="Natija hali yo\'q">…</span>';
                switch ($res['status']) {
                    case 'no_teacher_grade':
                        return '<span class="badge badge-amber" title="JN yoki MT bahosi qo\'yilmagan">' . __("O'qituvchi bahosini qo'ymagan") . $suffix . '</span>';
                    case 'absent':
                        return '<span class="badge badge-gray" title="OSKE/TEST natijasi yo\'q">' . __("Imtihonga kelmagan") . $suffix . '</span>';
                    case 'failed':
                        return '<span class="badge badge-red" title="Bosqichlardan biri 60 dan past">' . __("Yiqildi") . $suffix . '</span>';
                    case 'passed':
                        return '<span class="badge badge-green" style="font-weight:700;">' . $res['value'] . $suffix . '</span>';
                    default:
                        return '<span class="rtm-await">…</span>';
                }
            };
        @endphp

        @if($activeTab === 'groups')
            <form method="POST" action="{{ route('admin.retake-test-markazi.generate-yn-oldi-word') }}" id="retake-yn-word-form">
                @csrf
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    @if($groups->isEmpty())
                        <div class="p-10 text-center text-sm text-gray-500">{{ __("Tasdiqlangan qayta o'qish guruhlari yo'q") }}</div>
                    @else
                        <div class="p-3 border-b border-gray-100 flex items-center justify-between gap-3">
                            <div class="text-sm text-gray-600">{{ __("Tanlangan guruhlar uchun YN oldi Word hosil qiling") }}</div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                                {{ __("YN oldi word") }}
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="journal-table">
                                <thead>
                                <tr>
                                    <th class="th-num" onclick="event.stopPropagation();">
                                        <input type="checkbox" id="select-all-retake-yn" class="rounded border-gray-300">
                                    </th>
                                    <th>{{ __("Fan") }}</th>
                                    <th>{{ __("Semestr") }}</th>
                                    <th>{{ __("Yopilish shakli") }}</th>
                                    <th>{{ __("O'qituvchi") }}</th>
                                    <th>{{ __("OSKE / TEST sanasi") }}</th>
                                    <th style="text-align:center;">{{ __("Talabalar") }}</th>
                                    <th>{{ __("Yuborilgan") }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($groups as $g)
                                    <tr onclick="window.location='{{ route('admin.retake-test-markazi.show', $g->id) }}'">
                                        <td class="td-num" onclick="event.stopPropagation();">
                                            <input type="checkbox" name="group_ids[]" value="{{ $g->id }}" class="retake-yn-group-checkbox rounded border-gray-300">
                                        </td>
                                        <td><span class="text-cell text-subject">{{ $g->subject_name }}</span></td>
                                        <td><span class="badge badge-teal">{{ $g->semester_name ?? '—' }}</span></td>
                                        <td>@php $b = $atype($g->assessment_type); @endphp<span class="badge {{ $b['cls'] }}">{{ $b['label'] }}</span></td>
                                        <td><span class="text-cell text-emerald">{{ $g->teacher_name ?? '—' }}</span></td>
                                        <td class="text-xs">
                                            @if($g->oske_date)<span class="badge badge-violet">OSKE: {{ $g->oske_date->format('d.m.Y') }}</span>@endif
                                            @if($g->test_date)<span class="badge badge-violet">TEST: {{ $g->test_date->format('d.m.Y') }}</span>@endif
                                            @if(!$g->oske_date && !$g->test_date)—@endif
                                        </td>
                                        <td style="text-align:center;"><span class="badge badge-blue">{{ $g->students_count }}</span></td>
                                        <td class="text-xs text-gray-500">{{ $g->sent_to_test_markazi_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-gray-100">{{ $groups->links() }}</div>
                    @endif
                </div>
            </form>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-3 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                    <form method="GET" action="{{ route('admin.retake-test-markazi.index') }}" class="flex items-center gap-2 flex-wrap">
                        <input type="hidden" name="tab" value="students">
                        @foreach(['education_type','department','specialty','level_code','semester_code','group','subject','per_page'] as $k)
                            @if(filled(request($k)))<input type="hidden" name="{{ $k }}" value="{{ request($k) }}">@endif
                        @endforeach
                        <input type="text" name="student_search" value="{{ $studentSearch }}"
                               placeholder="{{ __('Ism yoki ID...') }}"
                               class="w-56 rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <select name="sent_status" onchange="this.form.submit()"
                                class="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">{{ __("Barchasi") }}</option>
                            <option value="sent" {{ ($sentStatus ?? '') === 'sent' ? 'selected' : '' }}>{{ __("Yuborilgan") }}</option>
                            <option value="not_sent" {{ ($sentStatus ?? '') === 'not_sent' ? 'selected' : '' }}>{{ __("Yuborilmagan") }}</option>
                        </select>
                        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-semibold hover:bg-blue-700">
                            {{ __("Qidirish") }}
                        </button>
                        @if($studentSearch !== '' || ($sentStatus ?? '') !== '')
                            <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'students']) }}"
                               class="text-sm text-gray-500 hover:text-gray-800">{{ __("Tozalash") }}</a>
                        @endif
                    </form>

                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('admin.retake-test-markazi.generate-vedomost', array_merge(request()->except(['page','groups_page','students_page']), ['student_search' => $studentSearch, 'sent_status' => ($sentStatus ?? '')])) }}"
                           title="{{ __('Joriy filtr (fakultet/yo‘nalish/kurs/semestr/guruh/fan) bo‘yicha qayta o‘qish vedomostini yaratadi') }}"
                           style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:10px;background:#7c3aed;color:#fff;font-size:14px;font-weight:600;text-decoration:none;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            {{ __("Vedomost yaratish") }}
                        </a>
                        <a href="{{ route('admin.retake-test-markazi.students-excel', array_merge(request()->except(['page','groups_page','students_page']), ['student_search' => $studentSearch, 'sent_status' => ($sentStatus ?? '')])) }}"
                           style="display:inline-flex;align-items:center;padding:8px 16px;border-radius:10px;background:#2563eb;color:#fff;font-size:14px;font-weight:600;text-decoration:none;">
                            {{ __("Excel chiqarish") }}
                        </a>
                        <a href="{{ route('admin.retake-test-markazi.daily-allowed-students-word', ['student_search' => $studentSearch]) }}"
                           class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                            {{ __("Word chiqarish") }}
                        </a>
                    </div>
                </div>
                @if($sentApplications->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-500">{{ __("Talaba topilmadi") }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="journal-table" id="rtm-students-table">
                            <thead>
                            <tr>
                                <th class="th-num">#</th>
                                <th>{{ __("F.I.Sh") }}</th>
                                <th>{{ __("Kurs") }}</th>
                                <th>{{ __("Guruh") }}</th>
                                <th>{{ __("Fan") }}</th>
                                <th>{{ __("Ariza bergan fani") }}</th>
                                <th>{{ __("Yopilish shakli") }}</th>
                                <th>{{ __("Semestr") }}</th>
                                <th>{{ __("Holat") }}</th>
                                <th style="text-align:center;">JN</th>
                                <th style="text-align:center;">MT</th>
                                <th style="text-align:center;">OSKE</th>
                                <th style="text-align:center;">TEST</th>
                                <th style="text-align:center;">{{ __("Yakuniy natija") }}</th>
                            </tr>
                            {{-- Har bir ustun bo'yicha filtr — talabalar tabida barcha qatorlar bitta
                                 sahifada yuklanadi, shuning uchun filtr BUTUN ma'lumot bo'yicha ishlaydi. --}}
                            <tr class="rtm-filter-row">
                                <th class="rtm-fcell" style="text-align:center;">
                                    <button type="button" id="rtm-filter-clear" class="rtm-fclear" title="{{ __('Filtrlarni tozalash') }}">✕</button>
                                </th>
                                <th class="rtm-fcell"><input type="text" class="rtm-finput" data-filter-col="1" placeholder="{{ __('F.I.Sh...') }}"></th>
                                <th class="rtm-fcell"><select class="rtm-finput" data-filter-col="2"><option value="">{{ __('Barchasi') }}</option></select></th>
                                <th class="rtm-fcell"><select class="rtm-finput" data-filter-col="3"><option value="">{{ __('Barchasi') }}</option></select></th>
                                <th class="rtm-fcell"><input type="text" class="rtm-finput" data-filter-col="4" placeholder="{{ __('Fan...') }}"></th>
                                <th class="rtm-fcell"><input type="text" class="rtm-finput" data-filter-col="5" placeholder="{{ __('Fan...') }}"></th>
                                <th class="rtm-fcell"><select class="rtm-finput" data-filter-col="6"><option value="">{{ __('Barchasi') }}</option></select></th>
                                <th class="rtm-fcell"><select class="rtm-finput" data-filter-col="7"><option value="">{{ __('Barchasi') }}</option></select></th>
                                <th class="rtm-fcell"><select class="rtm-finput" data-filter-col="8"><option value="">{{ __('Barchasi') }}</option></select></th>
                                <th class="rtm-fcell"><input type="text" class="rtm-finput rtm-fnum" data-filter-col="9" placeholder="JN"></th>
                                <th class="rtm-fcell"><input type="text" class="rtm-finput rtm-fnum" data-filter-col="10" placeholder="MT"></th>
                                <th class="rtm-fcell"><input type="text" class="rtm-finput rtm-fnum" data-filter-col="11" placeholder="OSKE"></th>
                                <th class="rtm-fcell"><input type="text" class="rtm-finput rtm-fnum" data-filter-col="12" placeholder="TEST"></th>
                                <th class="rtm-fcell"><input type="text" class="rtm-finput" data-filter-col="13" placeholder="{{ __('Natija...') }}"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($sentApplications as $app)
                                @php
                                    $student = $app->group?->student;
                                    $retakeGroup = $app->retakeGroup;
                                    $mustaqil = $mustaqilMap[$app->id] ?? null;
                                    $rgId = $app->retake_group_id ?? $retakeGroup?->id;
                                    $at = $retakeGroup?->assessment_type;
                                    $needsOske = in_array($at, ['oske', 'oske_test'], true);
                                    $needsTest = in_array($at, ['test', 'oske_test', 'sinov', 'sinov_fan'], true);
                                    $isSinov = in_array($at, ['sinov', 'sinov_fan'], true);
                                    // Sinov fanlarda Sinov(test) bahosi = JN (avtomatik).
                                    $effTest = $isSinov ? $app->joriy_score : $app->test_score;
                                    $effTestDate = $isSinov ? $app->joriy_graded_at : $app->test_graded_at;
                                    $b = $atype($at);
                                @endphp
                                <tr class="rtm-srow" @if($rgId) onclick="window.location='{{ route('admin.retake-test-markazi.show', $rgId) }}'" @endif>
                                    <td class="td-num"><span class="rtm-srow-num">{{ ($sentApplications->currentPage() - 1) * $sentApplications->perPage() + $loop->iteration }}</span></td>
                                    <td>
                                        <span class="text-cell text-subject">{{ $student?->full_name ?? '—' }}</span>
                                        <span class="text-cell" style="color:#64748b;font-size:11px;">{{ $app->student_hemis_id }}</span>
                                    </td>
                                    <td><span class="badge badge-violet">{{ $student?->level_name ?? '—' }}</span></td>
                                    <td><span class="badge badge-indigo">{{ $student?->group_name ?? '—' }}</span></td>
                                    <td><span class="text-cell text-subject">{{ $retakeGroup?->subject_name ?? $app->subject_name }}</span></td>
                                    {{-- Ariza bergan fani — talaba yozgan fan (guruh fani boshqa bo'lishi mumkin,
                                         o'quv bo'limi o'xshash fanga biriktirgan bo'lsa). --}}
                                    <td><span class="text-cell" style="color:#475569;">{{ $app->subject_name ?? '—' }}</span></td>
                                    <td><span class="badge {{ $b['cls'] }}">{{ $b['label'] }}</span></td>
                                    {{-- Semestr — ARIZANIKI (guruh bir nechta semestrni birlashtirishi mumkin). --}}
                                    <td><span class="badge badge-teal">{{ $app->semester_name ?? $retakeGroup?->semester_name }}</span></td>
                                    <td>
                                        @if($app->sent_to_test_markazi_at)
                                            <span class="badge badge-green">{{ __("Yuborilgan") }}</span>
                                        @else
                                            <span class="badge badge-gray">{{ __("Yuborilmagan") }}</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">{!! $scoreCell(true, $app->joriy_score, 'badge-blue', $app->joriy_graded_at) !!}</td>
                                    <td style="text-align:center;">{!! $scoreCell(true, $mustaqil?->grade, 'badge-green', $mustaqil?->graded_at) !!}</td>
                                    <td style="text-align:center;">{!! $scoreCell($needsOske, $app->oske_score, 'badge-blue', $app->oske_graded_at) !!}</td>
                                    <td style="text-align:center;">{!! $scoreCell($needsTest, $effTest, 'badge-blue', $effTestDate) !!}</td>
                                    <td style="text-align:center;">{!! $finalCell($finalResultMap[$app->id] ?? null, $attemptsMap[$app->id] ?? 1) !!}</td>
                                </tr>
                            @endforeach
                            <tr id="rtm-srow-empty" style="display:none;">
                                <td colspan="14" class="p-6 text-center text-sm text-gray-500">{{ __("Filtr bo'yicha talaba topilmadi") }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-t border-gray-100">{{ $sentApplications->links() }}</div>
                @endif
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const selectAll = document.getElementById('select-all-retake-yn');
                const items = Array.from(document.querySelectorAll('.retake-yn-group-checkbox'));
                if (selectAll && items.length) {
                    selectAll.addEventListener('change', () => items.forEach(cb => cb.checked = selectAll.checked));
                }
            });
        </script>
    @endpush

    @push('scripts')
        {{-- Ustunlar bo'yicha client-side filtr (joriy sahifadagi qatorlar) --}}
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const table = document.getElementById('rtm-students-table');
                if (!table || !table.tBodies.length) return;

                const tbody = table.tBodies[0];
                const rows = Array.from(tbody.querySelectorAll('tr.rtm-srow'));
                const emptyRow = document.getElementById('rtm-srow-empty');
                const controls = Array.from(table.querySelectorAll('[data-filter-col]'));
                if (!controls.length || !rows.length) return;

                const norm = (s) => (s || '').toLowerCase().replace(/\s+/g, ' ').trim();
                const cellText = (row, idx) => norm(row.children[idx] ? row.children[idx].textContent : '');

                // Dropdownlarni joriy sahifadagi mavjud qiymatlar bilan to'ldirish.
                controls.forEach(function (ctrl) {
                    if (ctrl.tagName !== 'SELECT') return;
                    const idx = parseInt(ctrl.dataset.filterCol, 10);
                    const seen = {};
                    const values = [];
                    rows.forEach(function (r) {
                        const raw = (r.children[idx] ? r.children[idx].textContent : '').replace(/\s+/g, ' ').trim();
                        if (raw && !seen[raw]) { seen[raw] = true; values.push(raw); }
                    });
                    values.sort(function (a, b) { return a.localeCompare(b, 'uz', { numeric: true }); });
                    values.forEach(function (v) {
                        const o = document.createElement('option');
                        o.value = v;
                        o.textContent = v;
                        ctrl.appendChild(o);
                    });
                });

                function applyFilters() {
                    let visible = 0;
                    rows.forEach(function (row) {
                        let ok = true;
                        for (let i = 0; i < controls.length; i++) {
                            const ctrl = controls[i];
                            const val = ctrl.value;
                            if (!val) continue;
                            const idx = parseInt(ctrl.dataset.filterCol, 10);
                            const cell = cellText(row, idx);
                            if (ctrl.tagName === 'SELECT') {
                                if (cell !== norm(val)) { ok = false; break; }
                            } else {
                                if (cell.indexOf(norm(val)) === -1) { ok = false; break; }
                            }
                        }
                        row.style.display = ok ? '' : 'none';
                        if (ok) {
                            visible++;
                            const num = row.querySelector('.rtm-srow-num');
                            if (num) num.textContent = visible;
                        }
                    });
                    if (emptyRow) emptyRow.style.display = visible === 0 ? '' : 'none';
                }

                controls.forEach(function (ctrl) {
                    ctrl.addEventListener('input', applyFilters);
                    ctrl.addEventListener('change', applyFilters);
                    // Filtr kataklariga bosilganda qatorga o'tib ketmasin.
                    ctrl.addEventListener('click', function (e) { e.stopPropagation(); });
                });

                const clearBtn = document.getElementById('rtm-filter-clear');
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        controls.forEach(function (ctrl) { ctrl.value = ''; });
                        applyFilters();
                    });
                }
            });
        </script>
    @endpush
</x-teacher-app-layout>
