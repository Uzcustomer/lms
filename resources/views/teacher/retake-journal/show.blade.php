<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.retake-journal.index') }}" class="text-sm text-blue-600 hover:underline">
                ← {{ __("Jurnal ro'yxati") }}
            </a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $group->name }}
            </h2>
            @if(!$canEdit)
                <span class="px-2 py-0.5 text-[11px] font-medium bg-gray-200 text-gray-700 rounded-full">{{ __("Faqat ko'rish") }}</span>
            @elseif(!$isEditable)
                <span class="px-2 py-0.5 text-[11px] font-medium bg-amber-100 text-amber-800 rounded-full">{{ __("Muddat tugagan") }}</span>
            @endif
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="retakeJournal({
             saveUrl: '{{ route('admin.retake-journal.save-grade', $group->id) }}',
             csrf: '{{ csrf_token() }}',
             canEdit: @js($canEdit),
         })">

        {{-- Guruh ma'lumotlari --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Fan") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">{{ $group->subject_name }}</p>
                    <p class="text-[11px] text-gray-500">{{ $group->semester_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("O'qituvchi") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">{{ $group->teacher_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Sanalar") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5 text-xs">
                        {{ $group->start_date->format('Y-m-d') }} → {{ $group->end_date->format('Y-m-d') }}
                    </p>
                    <p class="text-[11px] text-gray-500">{{ count($dates) }} {{ __("kun") }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Baholash") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">
                        @php
                            $atypeLabels = [
                                'oske' => 'OSKE',
                                'test' => 'TEST',
                                'oske_test' => 'OSKE + TEST',
                                'sinov_fan' => __("Sinov fan"),
                            ];
                        @endphp
                        {{ $atypeLabels[$group->assessment_type] ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Talabalar") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">{{ count($applications) }}</p>
                </div>
            </div>
        </div>

        {{-- Jurnal jadvali --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if(count($applications) === 0)
                <div class="p-10 text-center text-sm text-gray-500">
                    {{ __("Bu guruhda talabalar yo'q") }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-2 py-2 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 z-10 border-r border-gray-200" style="min-width:50px;">T/R</th>
                                <th class="px-2 py-2 text-left font-medium text-gray-500 sticky bg-gray-50 z-10 border-r border-gray-200" style="left:50px;min-width:240px;">{{ __("F.I.Sh") }}</th>
                                <th class="px-2 py-2 text-center font-medium text-amber-700 bg-amber-50 border-r border-gray-200" style="min-width:80px;" title="{{ __('Registrator tasdiqlagan oldingi joriy bahosi') }}">
                                    {{ __("Joriy") }}<br><span class="text-[10px] text-gray-500">({{ __("eski") }})</span>
                                </th>
                                <th class="px-2 py-2 text-center font-medium text-amber-700 bg-amber-50 border-r border-gray-200" style="min-width:80px;" title="{{ __('Registrator tasdiqlagan oldingi mustaqil bahosi') }}">
                                    {{ __("Mustaqil") }}<br><span class="text-[10px] text-gray-500">({{ __("eski") }})</span>
                                </th>
                                @foreach($dates as $d)
                                    <th class="px-1 py-2 text-center font-medium text-gray-600 border-r border-gray-200" style="min-width:60px;">
                                        {{ \Carbon\Carbon::parse($d)->format('d/m') }}
                                    </th>
                                @endforeach
                                <th class="px-2 py-2 text-center font-medium text-blue-700 bg-blue-50" style="min-width:70px;">{{ __("O'rtacha") }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($applications as $i => $app)
                                @php
                                    $student = $app->group->student ?? null;
                                    $rowGrades = $gradesMap[$app->id] ?? [];
                                    $rowGradeValues = collect($rowGrades)->map(fn ($g) => $g->grade)->filter(fn ($v) => $v !== null);
                                    $avg = $rowGradeValues->isNotEmpty() ? round($rowGradeValues->avg(), 1) : null;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-2 py-1.5 text-gray-600 sticky left-0 bg-white border-r border-gray-200">{{ $i + 1 }}</td>
                                    <td class="px-2 py-1.5 sticky bg-white border-r border-gray-200" style="left:50px;">
                                        <span class="text-gray-900 font-medium">{{ $student?->full_name ?? '—' }}</span>
                                        <span class="block text-[10px] text-gray-500">{{ $app->student_hemis_id }}</span>
                                    </td>
                                    <td class="px-2 py-1.5 text-center text-amber-800 bg-amber-50 border-r border-gray-200 font-medium">
                                        {{ $app->previous_joriy_grade !== null ? rtrim(rtrim(number_format($app->previous_joriy_grade, 2, '.', ''), '0'), '.') : '—' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center text-amber-800 bg-amber-50 border-r border-gray-200 font-medium">
                                        {{ $app->previous_mustaqil_grade !== null ? rtrim(rtrim(number_format($app->previous_mustaqil_grade, 2, '.', ''), '0'), '.') : '—' }}
                                    </td>
                                    @foreach($dates as $d)
                                        @php
                                            $cell = $rowGrades[$d] ?? null;
                                            $val = $cell?->grade;
                                        @endphp
                                        <td class="px-0 py-0 text-center border-r border-gray-200 cell"
                                            data-app-id="{{ $app->id }}"
                                            data-date="{{ $d }}">
                                            <input type="text"
                                                   inputmode="numeric"
                                                   maxlength="3"
                                                   value="{{ $val !== null ? rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.') : '' }}"
                                                   @if(!$canEdit) readonly @endif
                                                   @change="saveCell($event, {{ $app->id }}, '{{ $d }}')"
                                                   class="w-full px-1 py-1.5 text-xs text-center bg-transparent border-0 focus:ring-2 focus:ring-blue-300 focus:bg-white outline-none {{ $canEdit ? '' : 'cursor-default' }}"
                                                   @if($val !== null && $val < 60) style="color:#b91c1c;font-weight:600;" @endif
                                                   @if($val !== null && $val >= 60 && $val < 75) style="color:#b45309;" @endif
                                                   @if($val !== null && $val >= 75) style="color:#15803d;font-weight:600;" @endif
                                                   >
                                        </td>
                                    @endforeach
                                    <td class="px-2 py-1.5 text-center bg-blue-50 font-semibold {{ $avg !== null && $avg < 60 ? 'text-red-700' : 'text-blue-700' }}">
                                        {{ $avg !== null ? $avg : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Eslatma --}}
        @if($canEdit)
            <div class="mt-3 text-xs text-gray-500">
                💡 {{ __("Bahoni katakka kiritib, boshqa joyga bosing — avtomatik saqlanadi. 0 dan 100 gacha qiymat. Bo'sh qoldirish uchun matnni o'chiring.") }}
            </div>
        @endif

        @if(!$isEditable)
            <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900">
                ⚠️ {{ __("Bu guruh muddati tugagan. Baholarni faqat super-admin tahrirlay oladi.") }}
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            function retakeJournal({ saveUrl, csrf, canEdit }) {
                return {
                    saveUrl, csrf, canEdit,
                    saving: {},

                    async saveCell(e, appId, date) {
                        if (!this.canEdit) return;
                        const input = e.target;
                        const raw = (input.value || '').trim();
                        const grade = raw === '' ? null : Number(raw);

                        if (grade !== null && (isNaN(grade) || grade < 0 || grade > 100)) {
                            input.classList.add('bg-red-50');
                            alert('Baho 0 dan 100 gacha bo\'lishi kerak');
                            return;
                        }

                        const key = `${appId}|${date}`;
                        if (this.saving[key]) return;
                        this.saving[key] = true;

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
                                    lesson_date: date,
                                    grade: grade,
                                }),
                            });
                            const data = await res.json();
                            if (!res.ok || !data.success) {
                                alert(data.message || 'Saqlashda xato');
                                input.classList.add('bg-red-50');
                            } else {
                                input.classList.remove('bg-red-50');
                                input.classList.add('bg-green-50');
                                setTimeout(() => input.classList.remove('bg-green-50'), 600);
                                // Rang yangilanishi
                                const v = data.grade !== null ? Number(data.grade) : null;
                                input.style.color = v === null ? '' : (v < 60 ? '#b91c1c' : (v < 75 ? '#b45309' : '#15803d'));
                                input.style.fontWeight = (v !== null && (v < 60 || v >= 75)) ? '600' : '';
                            }
                        } catch (err) {
                            alert('Tarmoq xatosi');
                            input.classList.add('bg-red-50');
                        } finally {
                            delete this.saving[key];
                        }
                    },
                };
            }
        </script>
    @endpush
</x-teacher-app-layout>
