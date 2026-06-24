<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.retake-test-markazi.index') }}" class="text-sm text-blue-600 hover:underline">
                ← {{ __("Test markazi guruhlari") }}
            </a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $group->name }}</h2>
        </div>
    </x-slot>

    @include('partials._journal_table_styles')
    <style>
        /* Bu sahifada qatorlar bosiladigan emas — kursor oddiy holatda. */
        .journal-table tbody tr { cursor: default; }
    </style>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="testMarkazi({
            saveUrl: '{{ route('admin.retake-test-markazi.save-score', $group->id) }}',
            loadUrl: '{{ route('admin.retake-test-markazi.load-from-diagnostika', $group->id) }}',
            csrf: '{{ csrf_token() }}',
         })">

        @php
            $needsOske = in_array($group->assessment_type, ['oske', 'oske_test'], true);
            $needsTest = in_array($group->assessment_type, ['test', 'oske_test'], true);
            $atypeLabels = [
                'oske' => 'OSKE',
                'test' => 'TEST',
                'oske_test' => 'OSKE + TEST',
            ];
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Fan") }}</p>
                    <p class="font-medium text-gray-900">{{ $group->subject_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Tur") }}</p>
                    <p class="font-medium text-gray-900">{{ $atypeLabels[$group->assessment_type] ?? '—' }}</p>
                </div>
                @if($group->oske_date)
                    <div>
                        <p class="text-xs text-gray-500 uppercase">OSKE {{ __("sanasi") }}</p>
                        <p class="font-medium text-gray-900">{{ $group->oske_date->format('Y-m-d') }}</p>
                    </div>
                @endif
                @if($group->test_date)
                    <div>
                        <p class="text-xs text-gray-500 uppercase">TEST {{ __("sanasi") }}</p>
                        <p class="font-medium text-gray-900">{{ $group->test_date->format('Y-m-d') }}</p>
                    </div>
                @endif
            </div>
            <div class="mt-3 flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.retake-journal.vedomost', $group->id) }}"
                   class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                    📊 {{ __("Vedomost (PDF)") }}
                </a>
                <form method="POST" action="{{ route('admin.retake-test-markazi.generate-yn-oldi-word') }}" class="inline">
                    @csrf
                    <input type="hidden" name="group_ids[]" value="{{ $group->id }}">
                    <button type="submit"
                            class="px-3 py-1.5 text-xs bg-emerald-600 text-white rounded hover:bg-emerald-700">
                        📝 {{ __("YN qaydnoma yaratish") }}
                    </button>
                </form>
                @if($needsOske || $needsTest)
                    <button type="button" @click="loadFromDiagnostika()" :disabled="loading"
                       class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                        <span x-show="!loading">⬇️ {{ __("Diagnostika orqali yuklash") }}</span>
                        <span x-show="loading">{{ __("Yuklanmoqda...") }}</span>
                    </button>
                    <span class="text-xs text-gray-500">{{ __("Faqat shu sessiya (fasl/o'quv yili) natijalari olinadi") }}</span>
                @endif
            </div>
        </div>

        @php
            $studentsCol = $applications->map(fn ($a) => $a->group->student ?? null)->filter();
            $faculties  = $studentsCol->pluck('department_name')->filter()->unique()->sort()->values();
            $directions = $studentsCol->pluck('specialty_name')->filter()->unique()->sort()->values();
            $kurslar    = $studentsCol->pluck('level_name')->filter()->unique()->sort()->values();
            $guruhlar   = $studentsCol->pluck('group_name')->filter()->unique()->sort()->values();
            $fanlar     = $applications->pluck('subject_name')->filter()->push($group->subject_name)->filter()->unique()->sort()->values();
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- Filtrlar: Fakultet, Yo'nalish, Kurs, Guruh, Fan --}}
            <div class="p-3 border-b border-gray-100 grid grid-cols-2 md:grid-cols-5 gap-2">
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 uppercase mb-1">{{ __("Fakultet") }}</label>
                    <select data-rtm-filter="faculty" class="w-full rounded-lg border-gray-300 text-xs focus:border-blue-500 focus:ring-blue-500">
                        <option value="">{{ __("Barchasi") }}</option>
                        @foreach($faculties as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 uppercase mb-1">{{ __("Yo'nalish") }}</label>
                    <select data-rtm-filter="direction" class="w-full rounded-lg border-gray-300 text-xs focus:border-blue-500 focus:ring-blue-500">
                        <option value="">{{ __("Barchasi") }}</option>
                        @foreach($directions as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 uppercase mb-1">{{ __("Kurs") }}</label>
                    <select data-rtm-filter="kurs" class="w-full rounded-lg border-gray-300 text-xs focus:border-blue-500 focus:ring-blue-500">
                        <option value="">{{ __("Barchasi") }}</option>
                        @foreach($kurslar as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 uppercase mb-1">{{ __("Guruh") }}</label>
                    <select data-rtm-filter="group" class="w-full rounded-lg border-gray-300 text-xs focus:border-blue-500 focus:ring-blue-500">
                        <option value="">{{ __("Barchasi") }}</option>
                        @foreach($guruhlar as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-gray-500 uppercase mb-1">{{ __("Fan") }}</label>
                    <select data-rtm-filter="fan" class="w-full rounded-lg border-gray-300 text-xs focus:border-blue-500 focus:ring-blue-500">
                        <option value="">{{ __("Barchasi") }}</option>
                        @foreach($fanlar as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="journal-table" id="rtm-table">
                    <thead>
                    <tr>
                        <th class="th-num">T/R</th>
                        <th>{{ __("F.I.Sh") }}</th>
                        <th>{{ __("Holat") }}</th>
                        <th style="text-align:center;">{{ __("Amaliyot o'rt.") }}</th>
                        <th style="text-align:center;">{{ __("Mustaqil") }}</th>
                        @if($needsOske)
                            <th style="text-align:center; background:#eff6ff; color:#1d4ed8;">OSKE</th>
                        @endif
                        @if($needsTest)
                            <th style="text-align:center; background:#eff6ff; color:#1d4ed8;">TEST</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                        @foreach($applications as $i => $app)
                            @php
                                $student = $app->group->student ?? null;
                                $amaliyotAvg = $app->joriy_score !== null ? round((float) $app->joriy_score, 1) : null;
                                $mustaqil = $mustaqilMap[$app->id] ?? null;
                            @endphp
                        <tr class="rtm-row"
                            data-faculty="{{ $student?->department_name }}"
                            data-direction="{{ $student?->specialty_name }}"
                            data-kurs="{{ $student?->level_name }}"
                            data-group="{{ $student?->group_name }}"
                            data-fan="{{ $app->subject_name ?? $group->subject_name }}">
                            <td class="td-num rtm-num">{{ $i + 1 }}</td>
                            <td>
                                <span class="text-cell text-subject">{{ $student?->full_name ?? '—' }}</span>
                                <span class="text-cell" style="color:#64748b;font-size:11px;">{{ $app->student_hemis_id }}</span>
                            </td>
                            <td><span class="badge badge-green">{{ __("Testga ruxsat etilgan") }}</span></td>
                            <td style="text-align:center;"><span class="badge badge-blue">{{ $amaliyotAvg ?? '—' }}</span></td>
                            <td style="text-align:center;">
                                <span class="badge badge-teal">{{ $mustaqil?->grade !== null ? rtrim(rtrim(number_format($mustaqil->grade, 2, '.', ''), '0'), '.') : '—' }}</span>
                            </td>
                            @if($needsOske)
                                <td style="text-align:center; background:#eff6ff;" data-oske-cell="{{ $app->id }}">
                                    <span class="badge badge-blue">{{ $app->oske_score !== null ? rtrim(rtrim(number_format($app->oske_score, 2, '.', ''), '0'), '.') : '—' }}</span>
                                </td>
                            @endif
                            @if($needsTest)
                                <td style="text-align:center; background:#eff6ff;" data-test-cell="{{ $app->id }}">
                                    <span class="badge badge-blue">{{ $app->test_score !== null ? rtrim(rtrim(number_format($app->test_score, 2, '.', ''), '0'), '.') : '—' }}</span>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    <tr id="rtm-empty" style="display:none;">
                        <td colspan="7" class="p-6 text-center text-sm text-gray-500">{{ __("Filtr bo'yicha talaba topilmadi") }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3 text-xs text-gray-500">
            💡 {{ __("OSKE va TEST natijalari qo'lda kiritilmaydi — faqat diagnostika orqali (Test markazi → Sistemaga yuklash) avtomatik tushadi.") }}
        </div>
    </div>

    @push('scripts')
        <script>
            function testMarkazi({ saveUrl, loadUrl, csrf }) {
                return {
                    saveUrl, loadUrl, csrf, saving: {}, loading: false,
                    async loadFromDiagnostika() {
                        if (this.loading) return;
                        if (!confirm("Diagnostika orqali shu guruh sessiyasiga mos OSKE/TEST natijalari yuklansinmi?")) return;
                        this.loading = true;
                        try {
                            const res = await fetch(this.loadUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'Accept': 'application/json',
                                },
                            });
                            const data = await res.json();
                            if (!res.ok || !data.success) {
                                alert(data.message || 'Yuklashda xato');
                                return;
                            }
                            alert(data.message || 'Yuklandi');
                            window.location.reload();
                        } catch (e) {
                            alert('Tarmoq xatosi');
                        } finally {
                            this.loading = false;
                        }
                    },
                };
            }
        </script>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const selects = Array.from(document.querySelectorAll('[data-rtm-filter]'));
                const rows = Array.from(document.querySelectorAll('#rtm-table tbody tr.rtm-row'));
                const emptyRow = document.getElementById('rtm-empty');
                if (!selects.length || !rows.length) return;

                function applyFilters() {
                    const active = {};
                    selects.forEach(s => { active[s.dataset.rtmFilter] = s.value; });
                    let visible = 0;
                    rows.forEach(row => {
                        const ok = Object.keys(active).every(key => {
                            if (!active[key]) return true;
                            return (row.dataset[key] || '') === active[key];
                        });
                        row.style.display = ok ? '' : 'none';
                        if (ok) {
                            visible++;
                            const num = row.querySelector('.rtm-num');
                            if (num) num.textContent = visible;
                        }
                    });
                    if (emptyRow) emptyRow.style.display = visible === 0 ? '' : 'none';
                }

                selects.forEach(s => s.addEventListener('change', applyFilters));
                applyFilters();
            });
        </script>
    @endpush
</x-teacher-app-layout>
