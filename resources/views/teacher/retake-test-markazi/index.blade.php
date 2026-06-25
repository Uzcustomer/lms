<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish - Test markazi") }}
        </h2>
    </x-slot>

    @include('partials._journal_table_styles')
    <style>
        .col-filter {
            width: 100%;
            font-size: 11px;
            padding: 3px 6px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            font-weight: 400;
            text-transform: none;
        }
        .journal-table thead tr.filter-row th { padding: 4px 8px 8px; background: #eef2f8; border-bottom: 2px solid #cbd5e1; }
    </style>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="mb-4 flex items-center gap-2 border-b border-gray-200 bg-white rounded-t-xl px-4 pt-3 shadow-sm">
            <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'groups']) }}"
               class="px-5 py-3 text-sm font-semibold border-b-2 rounded-t-lg transition {{ $activeTab === 'groups' ? 'border-blue-600 text-blue-700 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">
                {{ __("Jami guruhlar") }}
            </a>
            <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'students']) }}"
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
        @endphp

        @if($activeTab === 'groups')
            @php
                $gFanlar = $groups->pluck('subject_name')->filter()->unique()->sort()->values();
                $gTeachers = $groups->pluck('teacher_name')->filter()->unique()->sort()->values();
                $gTurlar = $groups->pluck('assessment_type')->filter()->unique()->values();
            @endphp
            <form method="POST" action="{{ route('admin.retake-test-markazi.generate-yn-oldi-word') }}" id="retake-yn-word-form">
                @csrf
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    @if($groups->isEmpty())
                        <div class="p-10 text-center text-sm text-gray-500">
                            {{ __("Hozircha tasdiqlangan qayta o'qish guruhlari yo'q") }}
                        </div>
                    @else
                        <div class="p-3 border-b border-gray-100 flex items-center justify-between gap-3">
                            <div class="text-sm text-gray-600">{{ __("Tanlangan guruhlar uchun YN oldi Word hosil qiling") }}</div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                                {{ __("YN oldi word") }}
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="journal-table" id="groups-table">
                                <thead>
                                <tr>
                                    <th class="th-num" onclick="event.stopPropagation();">
                                        <input type="checkbox" id="select-all-retake-yn" class="rounded border-gray-300">
                                    </th>
                                    <th>{{ __("Fan") }}</th>
                                    <th>{{ __("O'qituvchi") }}</th>
                                    <th>{{ __("Tur") }}</th>
                                    <th>{{ __("OSKE / TEST sanasi") }}</th>
                                    <th style="text-align:center;">{{ __("Talabalar") }}</th>
                                    <th>{{ __("Yuborilgan") }}</th>
                                </tr>
                                <tr class="filter-row">
                                    <th></th>
                                    <th>
                                        <select class="col-filter" data-key="fan">
                                            <option value="">{{ __("Barchasi") }}</option>
                                            @foreach($gFanlar as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                                        </select>
                                    </th>
                                    <th>
                                        <select class="col-filter" data-key="teacher">
                                            <option value="">{{ __("Barchasi") }}</option>
                                            @foreach($gTeachers as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                                        </select>
                                    </th>
                                    <th>
                                        <select class="col-filter" data-key="tur">
                                            <option value="">{{ __("Barchasi") }}</option>
                                            @foreach($gTurlar as $v)<option value="{{ $v }}">{{ $atype($v)['label'] }}</option>@endforeach
                                        </select>
                                    </th>
                                    <th></th><th></th><th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($groups as $g)
                                    <tr class="data-row"
                                        data-fan="{{ $g->subject_name }}"
                                        data-teacher="{{ $g->teacher_name }}"
                                        data-tur="{{ $g->assessment_type }}"
                                        onclick="window.location='{{ route('admin.retake-test-markazi.show', $g->id) }}'">
                                        <td class="td-num" onclick="event.stopPropagation();">
                                            <input type="checkbox" name="group_ids[]" value="{{ $g->id }}" class="retake-yn-group-checkbox rounded border-gray-300">
                                        </td>
                                        <td>
                                            <span class="text-cell text-subject">{{ $g->subject_name }}</span>
                                            <span class="text-cell" style="color:#64748b;font-size:11px;">{{ $g->semester_name }}</span>
                                        </td>
                                        <td><span class="text-cell text-emerald">{{ $g->teacher_name ?? '—' }}</span></td>
                                        <td>@php $b = $atype($g->assessment_type); @endphp<span class="badge {{ $b['cls'] }}">{{ $b['label'] }}</span></td>
                                        <td class="text-xs">
                                            @if($g->oske_date)<span class="badge badge-violet">OSKE: {{ $g->oske_date->format('Y-m-d') }}</span>@endif
                                            @if($g->test_date)<span class="badge badge-violet">TEST: {{ $g->test_date->format('Y-m-d') }}</span>@endif
                                            @if(!$g->oske_date && !$g->test_date)—@endif
                                        </td>
                                        <td style="text-align:center;"><span class="badge badge-blue">{{ $g->students_count }}</span></td>
                                        <td class="text-xs text-gray-500">{{ $g->sent_to_test_markazi_at?->format('Y-m-d H:i') ?? '—' }}</td>
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
            @php
                $sStudents = $sentApplications->getCollection()->map(fn ($a) => $a->group?->student)->filter();
                $sKurslar = $sStudents->pluck('level_name')->filter()->unique()->sort()->values();
                $sGuruhlar = $sStudents->pluck('group_name')->filter()->unique()->sort()->values();
                $sFanlar = $sentApplications->getCollection()->map(fn ($a) => $a->retakeGroup?->subject_name ?? $a->subject_name)->filter()->unique()->sort()->values();
                $sSemestrlar = $sentApplications->getCollection()->map(fn ($a) => $a->retakeGroup?->semester_name ?? $a->semester_name)->filter()->unique()->sort()->values();
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-3 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                    <form method="GET" action="{{ route('admin.retake-test-markazi.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="students">
                        <input type="text" name="student_search" value="{{ $studentSearch }}"
                               placeholder="{{ __('Ism yoki ID...') }}"
                               class="w-64 rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-semibold hover:bg-blue-700">
                            {{ __("Qidirish") }}
                        </button>
                        @if($studentSearch !== '')
                            <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'students']) }}" class="text-sm text-gray-500 hover:text-gray-800">{{ __("Tozalash") }}</a>
                        @endif
                    </form>
                    <a href="{{ route('admin.retake-test-markazi.daily-allowed-students-word', ['student_search' => $studentSearch]) }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                        {{ __("Word chiqarish") }}
                    </a>
                </div>
                @if($sentApplications->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-500">{{ __("Hozircha talaba yo'q") }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="journal-table" id="students-table">
                            <thead>
                            <tr>
                                <th class="th-num">#</th>
                                <th>{{ __("F.I.Sh") }}</th>
                                <th>{{ __("Kurs") }}</th>
                                <th>{{ __("Guruh") }}</th>
                                <th>{{ __("Fan") }}</th>
                                <th>{{ __("Semestr") }}</th>
                                <th style="text-align:center;">JN</th>
                                <th style="text-align:center;">MT</th>
                                <th style="text-align:center; background:#eff6ff; color:#1d4ed8;">OSKE</th>
                                <th style="text-align:center; background:#eff6ff; color:#1d4ed8;">TEST</th>
                            </tr>
                            <tr class="filter-row">
                                <th></th><th></th>
                                <th><select class="col-filter" data-key="kurs"><option value="">{{ __("Barchasi") }}</option>@foreach($sKurslar as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select></th>
                                <th><select class="col-filter" data-key="group"><option value="">{{ __("Barchasi") }}</option>@foreach($sGuruhlar as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select></th>
                                <th><select class="col-filter" data-key="fan"><option value="">{{ __("Barchasi") }}</option>@foreach($sFanlar as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select></th>
                                <th><select class="col-filter" data-key="semestr"><option value="">{{ __("Barchasi") }}</option>@foreach($sSemestrlar as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select></th>
                                <th></th><th></th><th></th><th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($sentApplications as $app)
                                @php
                                    $student = $app->group?->student;
                                    $retakeGroup = $app->retakeGroup;
                                    $mustaqil = $mustaqilMap[$app->id] ?? null;
                                    $rgId = $app->retake_group_id ?? $retakeGroup?->id;
                                    $fmt = fn ($v) => $v !== null ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : '—';
                                @endphp
                                <tr class="data-row"
                                    data-kurs="{{ $student?->level_name }}"
                                    data-group="{{ $student?->group_name }}"
                                    data-fan="{{ $retakeGroup?->subject_name ?? $app->subject_name }}"
                                    data-semestr="{{ $retakeGroup?->semester_name ?? $app->semester_name }}"
                                    @if($rgId) onclick="window.location='{{ route('admin.retake-test-markazi.show', $rgId) }}'" @endif>
                                    <td class="td-num row-num">{{ ($sentApplications->currentPage() - 1) * $sentApplications->perPage() + $loop->iteration }}</td>
                                    <td>
                                        <span class="text-cell text-subject">{{ $student?->full_name ?? '—' }}</span>
                                        <span class="text-cell" style="color:#64748b;font-size:11px;">{{ $app->student_hemis_id }}</span>
                                    </td>
                                    <td><span class="badge badge-violet">{{ $student?->level_name ?? '—' }}</span></td>
                                    <td><span class="badge badge-indigo">{{ $student?->group_name ?? '—' }}</span></td>
                                    <td><span class="text-cell text-subject">{{ $retakeGroup?->subject_name ?? $app->subject_name }}</span></td>
                                    <td><span class="badge badge-teal">{{ $retakeGroup?->semester_name ?? $app->semester_name }}</span></td>
                                    <td style="text-align:center;"><span class="badge badge-blue">{{ $fmt($app->joriy_score) }}</span></td>
                                    <td style="text-align:center;"><span class="badge badge-green">{{ $fmt($mustaqil?->grade) }}</span></td>
                                    <td style="text-align:center; background:#eff6ff;"><span class="badge badge-blue">{{ $fmt($app->oske_score) }}</span></td>
                                    <td style="text-align:center; background:#eff6ff;"><span class="badge badge-blue">{{ $fmt($app->test_score) }}</span></td>
                                </tr>
                            @endforeach
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

                function initColumnFilters(tableId) {
                    const table = document.getElementById(tableId);
                    if (!table) return;
                    const selects = Array.from(table.querySelectorAll('thead select.col-filter'));
                    const rows = Array.from(table.querySelectorAll('tbody tr.data-row'));
                    if (!selects.length || !rows.length) return;
                    function apply() {
                        const active = {};
                        selects.forEach(s => active[s.dataset.key] = s.value);
                        let visible = 0;
                        rows.forEach(r => {
                            const ok = Object.keys(active).every(k => !active[k] || (r.dataset[k] || '') === active[k]);
                            r.style.display = ok ? '' : 'none';
                            if (ok) { visible++; const n = r.querySelector('.row-num'); if (n) n.textContent = visible; }
                        });
                    }
                    selects.forEach(s => s.addEventListener('change', apply));
                    apply();
                }
                initColumnFilters('groups-table');
                initColumnFilters('students-table');
            });
        </script>
    @endpush
</x-teacher-app-layout>
