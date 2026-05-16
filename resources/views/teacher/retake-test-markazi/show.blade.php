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

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="testMarkazi({
            saveUrl: '{{ route('admin.retake-test-markazi.save-score', $group->id) }}',
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
            <div class="mt-3 flex items-center gap-2">
                <a href="{{ route('admin.retake-journal.vedomost', $group->id) }}"
                   class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                    📊 {{ __("Vedomost (PDF)") }}
                </a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">T/R</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("F.I.Sh") }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">HEMIS ID</th>
                        <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase">{{ __("Amaliyot o'rt.") }}</th>
                        <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase">{{ __("Mustaqil") }}</th>
                        @if($needsOske)
                            <th class="px-3 py-2 text-center font-medium text-blue-700 uppercase bg-blue-50">OSKE</th>
                        @endif
                        @if($needsTest)
                            <th class="px-3 py-2 text-center font-medium text-blue-700 uppercase bg-blue-50">TEST</th>
                        @endif
                        <th class="px-3 py-2 text-center font-medium text-green-700 uppercase bg-green-50">{{ __("Yakuniy") }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($applications as $i => $app)
                        @php
                            $student = $app->group->student ?? null;
                            $rowGrades = collect($gradesMap[$app->id] ?? [])
                                ->map(fn ($g) => $g->grade)
                                ->filter(fn ($v) => $v !== null);
                            $amaliyotAvg = $rowGrades->isNotEmpty() ? round($rowGrades->avg(), 1) : null;
                            $mustaqil = $mustaqilMap[$app->id] ?? null;
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-gray-600">{{ $i + 1 }}</td>
                            <td class="px-3 py-2 text-gray-900 font-medium">{{ $student?->full_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $app->student_hemis_id }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $amaliyotAvg ?? '—' }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">
                                {{ $mustaqil?->grade !== null ? rtrim(rtrim(number_format($mustaqil->grade, 2, '.', ''), '0'), '.') : '—' }}
                            </td>
                            @if($needsOske)
                                <td class="px-3 py-2 text-center bg-blue-50">
                                    <input type="text"
                                           inputmode="numeric"
                                           maxlength="3"
                                           value="{{ $app->oske_score !== null ? rtrim(rtrim(number_format($app->oske_score, 2, '.', ''), '0'), '.') : '' }}"
                                           data-oske="{{ $app->id }}"
                                           @change="saveScore({{ $app->id }})"
                                           class="w-16 px-2 py-1 text-xs text-center border border-blue-300 rounded focus:ring-2 focus:ring-blue-400 outline-none">
                                </td>
                            @endif
                            @if($needsTest)
                                <td class="px-3 py-2 text-center bg-blue-50">
                                    <input type="text"
                                           inputmode="numeric"
                                           maxlength="3"
                                           value="{{ $app->test_score !== null ? rtrim(rtrim(number_format($app->test_score, 2, '.', ''), '0'), '.') : '' }}"
                                           data-test="{{ $app->id }}"
                                           @change="saveScore({{ $app->id }})"
                                           class="w-16 px-2 py-1 text-xs text-center border border-blue-300 rounded focus:ring-2 focus:ring-blue-400 outline-none">
                                </td>
                            @endif
                            <td class="px-3 py-2 text-center bg-green-50 font-bold {{ $app->final_grade_value !== null && $app->final_grade_value < 60 ? 'text-red-700' : 'text-green-700' }}"
                                data-final="{{ $app->id }}">
                                {{ $app->final_grade_value !== null ? rtrim(rtrim(number_format($app->final_grade_value, 2, '.', ''), '0'), '.') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3 text-xs text-gray-500">
            💡 {{ __("OSKE va TEST natijalarini katakka kiritib, boshqa joyga bosing — avtomatik saqlanadi va yakuniy baho qayta hisoblanadi.") }}
        </div>
    </div>

    @push('scripts')
        <script>
            function testMarkazi({ saveUrl, csrf }) {
                return {
                    saveUrl, csrf, saving: {},
                    async saveScore(appId) {
                        const oskeInp = document.querySelector(`input[data-oske="${appId}"]`);
                        const testInp = document.querySelector(`input[data-test="${appId}"]`);

                        const oske = oskeInp ? (oskeInp.value || '').trim() : '';
                        const test = testInp ? (testInp.value || '').trim() : '';

                        const oskeNum = oske === '' ? null : Number(oske);
                        const testNum = test === '' ? null : Number(test);

                        if ((oskeNum !== null && (isNaN(oskeNum) || oskeNum < 0 || oskeNum > 100)) ||
                            (testNum !== null && (isNaN(testNum) || testNum < 0 || testNum > 100))) {
                            alert("Baho 0 dan 100 gacha bo'lishi kerak");
                            return;
                        }

                        if (this.saving[appId]) return;
                        this.saving[appId] = true;

                        try {
                            const res = await fetch(this.saveUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    application_id: appId,
                                    oske_score: oskeNum,
                                    test_score: testNum,
                                }),
                            });
                            const data = await res.json();
                            if (!res.ok || !data.success) {
                                alert(data.message || 'Saqlashda xato');
                                return;
                            }
                            const finalCell = document.querySelector(`td[data-final="${appId}"]`);
                            if (finalCell) {
                                const f = data.final_grade !== null ? Number(data.final_grade) : null;
                                finalCell.textContent = f === null ? '—' : f;
                                finalCell.classList.remove('text-red-700', 'text-green-700');
                                if (f !== null) {
                                    finalCell.classList.add(f < 60 ? 'text-red-700' : 'text-green-700');
                                }
                            }
                            if (oskeInp) {
                                oskeInp.classList.add('bg-green-50');
                                setTimeout(() => oskeInp.classList.remove('bg-green-50'), 600);
                            }
                            if (testInp) {
                                testInp.classList.add('bg-green-50');
                                setTimeout(() => testInp.classList.remove('bg-green-50'), 600);
                            }
                        } catch (e) {
                            alert('Tarmoq xatosi');
                        } finally {
                            delete this.saving[appId];
                        }
                    },
                };
            }
        </script>
    @endpush
</x-teacher-app-layout>
