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

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="journal-table">
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
                        <th style="text-align:center; background:#ecfdf5; color:#15803d;">{{ __("Yakuniy") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                        @foreach($applications as $i => $app)
                            @php
                                $student = $app->group->student ?? null;
                                // Test markazi oynasida birinchi ustun — retake jurnalidagi yagona JN bahosi.
                                // Eski kunlik baholar emas, aynan joriy_score ko'rsatiladi.
                                $amaliyotAvg = $app->joriy_score !== null ? round((float) $app->joriy_score, 1) : null;
                                $mustaqil = $mustaqilMap[$app->id] ?? null;
                                $finalVal = $app->final_grade_value;
                            @endphp
                        <tr>
                            <td class="td-num">{{ $i + 1 }}</td>
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
                            <td style="text-align:center; background:#ecfdf5;" data-final="{{ $app->id }}">
                                @if($finalVal !== null)
                                    <span class="badge {{ $finalVal < 60 ? 'badge-red' : 'badge-green' }}">{{ rtrim(rtrim(number_format($finalVal, 2, '.', ''), '0'), '.') }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
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
</x-teacher-app-layout>
