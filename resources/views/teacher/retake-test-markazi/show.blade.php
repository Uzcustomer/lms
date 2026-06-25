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
                <button type="button" onclick="openYnWeightsModal()"
                        class="px-3 py-1.5 text-xs bg-emerald-600 text-white rounded hover:bg-emerald-700">
                    📝 {{ __("YN qaydnoma yaratish") }}
                </button>
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

        {{-- YN qaydnoma vazn taqsimlash modali (asosiy jurnaldagidek → Excel) --}}
        @php
            // Standart vaznlar — asosiy jurnaldagi (YN qaydnoma) qiymatlar bilan bir xil.
            switch ($group->assessment_type) {
                case 'oske':      $dJn=50; $dMt=20; $dOn=0; $dOski=30; $dTest=0;  break;
                case 'test':      $dJn=50; $dMt=20; $dOn=0; $dOski=0;  $dTest=30; break;
                case 'oske_test': $dJn=50; $dMt=20; $dOn=0; $dOski=15; $dTest=15; break;
                case 'sinov':
                case 'sinov_fan': $dJn=50; $dMt=20; $dOn=0; $dOski=0;  $dTest=30; break;
                default:          $dJn=80; $dMt=20; $dOn=0; $dOski=0;  $dTest=0;  break;
            }
        @endphp
        <div id="yn-weights-modal" class="fixed inset-0 z-50 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeYnWeightsModal()"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-md relative">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800">{{ __("Vaznlarni taqsimlang") }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ __("Jami 100 bo'lishi kerak") }}</p>
                    </div>
                    <div class="px-6 py-4 space-y-3">
                        @foreach([['jn','JN',$dJn],['mt','MT',$dMt],['on','ON',$dOn],['oski','OSKI',$dOski],['test','Test',$dTest]] as $w)
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-semibold text-gray-700 w-20">{{ $w[1] }}</label>
                                <input type="number" id="yn-weight-{{ $w[0] }}" min="0" max="100" value="{{ $w[2] }}"
                                    class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-center text-sm font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    oninput="updateYnWeightsTotal()">
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                            <span class="text-sm font-bold text-gray-800">{{ __("Jami") }}:</span>
                            <span id="yn-weights-total" class="text-lg font-bold text-green-600">100</span>
                        </div>
                        <p id="yn-weights-error" class="text-sm text-red-600 hidden">{{ __("Jami 100 bo'lishi kerak!") }}</p>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeYnWeightsModal()"
                            class="px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition text-sm">
                            {{ __("Bekor qilish") }}
                        </button>
                        <button type="button" id="btn-yn-weights-submit" onclick="submitYnQaydnoma()"
                            class="px-5 py-2.5 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition shadow-sm text-sm">
                            {{ __("Yaratish") }}
                        </button>
                    </div>
                </div>
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
                        <th>{{ __("Fakultet") }}</th>
                        <th>{{ __("Yo'nalish") }}</th>
                        <th>{{ __("Kurs") }}</th>
                        <th>{{ __("Guruh") }}</th>
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
                                // Testga ruxsat: JN >= 60 VA MT >= 60 (YN-oldi / jurnal mantig'i)
                                $jnVal = $app->joriy_score !== null ? (float) $app->joriy_score : null;
                                $mtVal = $mustaqil?->grade !== null ? (float) $mustaqil->grade : null;
                                $allowed = $jnVal !== null && $mtVal !== null && $jnVal >= 60 && $mtVal >= 60;
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
                            <td><span class="text-cell text-emerald">{{ $student?->department_name ?? '—' }}</span></td>
                            <td><span class="text-cell" style="color:#0e7490;">{{ $student?->specialty_name ?? '—' }}</span></td>
                            <td><span class="badge badge-violet">{{ $student?->level_name ?? '—' }}</span></td>
                            <td><span class="badge badge-indigo">{{ $student?->group_name ?? '—' }}</span></td>
                            <td>
                                @if($allowed)
                                    <span class="badge badge-green">{{ __("Testga ruxsat etilgan") }}</span>
                                @else
                                    <span class="badge badge-red" title="JN va MT 60 dan past — testga ruxsat yo'q">{{ __("Ruxsat yo'q") }}</span>
                                @endif
                            </td>
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
                        <td colspan="11" class="p-6 text-center text-sm text-gray-500">{{ __("Filtr bo'yicha talaba topilmadi") }}</td>
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

    @push('scripts')
        <script>
            function openYnWeightsModal() {
                document.getElementById('yn-weights-modal').classList.remove('hidden');
                updateYnWeightsTotal();
            }
            function closeYnWeightsModal() {
                document.getElementById('yn-weights-modal').classList.add('hidden');
            }
            function ynWeightVal(id) { return parseInt(document.getElementById(id).value) || 0; }
            function updateYnWeightsTotal() {
                const total = ynWeightVal('yn-weight-jn') + ynWeightVal('yn-weight-mt') + ynWeightVal('yn-weight-on')
                    + ynWeightVal('yn-weight-oski') + ynWeightVal('yn-weight-test');
                const totalEl = document.getElementById('yn-weights-total');
                const errEl = document.getElementById('yn-weights-error');
                totalEl.textContent = total;
                const ok = total === 100;
                totalEl.style.color = ok ? '#16a34a' : '#dc2626';
                errEl.classList.toggle('hidden', ok);
                document.getElementById('btn-yn-weights-submit').disabled = !ok;
            }
            function submitYnQaydnoma() {
                const jn = ynWeightVal('yn-weight-jn'), mt = ynWeightVal('yn-weight-mt'), on = ynWeightVal('yn-weight-on'),
                    oski = ynWeightVal('yn-weight-oski'), test = ynWeightVal('yn-weight-test');
                if (jn + mt + on + oski + test !== 100) { alert("Vaznlar jami 100 bo'lishi kerak!"); return; }

                const btn = document.getElementById('btn-yn-weights-submit');
                btn.disabled = true;
                const original = btn.textContent;
                btn.textContent = 'Yuklanmoqda...';

                fetch('{{ route('admin.retake-test-markazi.yn-qaydnoma', $group->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/octet-stream',
                    },
                    body: JSON.stringify({
                        weight_jn: jn, weight_mt: mt, weight_on: on, weight_oski: oski, weight_test: test,
                    })
                })
                .then(async (response) => {
                    if (!response.ok) {
                        let msg = 'Server xatosi';
                        try { const j = await response.json(); msg = j.error || msg; } catch (e) {}
                        throw new Error(msg);
                    }
                    const cd = response.headers.get('Content-Disposition');
                    let fileName = 'yn_qaydnoma.xlsx';
                    if (cd) { const m = cd.match(/filename="?([^";\n]+)"?/); if (m && m[1]) fileName = m[1]; }
                    const blob = await response.blob();
                    return { blob, fileName };
                })
                .then(({ blob, fileName }) => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url; a.download = fileName;
                    document.body.appendChild(a); a.click(); a.remove();
                    window.URL.revokeObjectURL(url);
                    closeYnWeightsModal();
                })
                .catch((err) => alert('Xatolik: ' + err.message))
                .finally(() => { btn.disabled = false; btn.textContent = original; });
            }
        </script>
    @endpush
</x-teacher-app-layout>
