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
             gradeMustaqilUrl: '{{ route('admin.retake-journal.mustaqil-grade', $group->id) }}',
             csrf: '{{ csrf_token() }}',
             canEdit: @js($canEdit),
             tab: 'amaliyot',
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

        {{-- Lock / Vedomost / Test markazi paneli --}}
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-4 mb-4 flex items-center justify-between flex-wrap gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-900">
                    @if($group->is_locked)
                        🔒 {{ __("Guruh yopilgan — yakuniy baholar shakllangan") }}
                    @else
                        📝 {{ __("Guruh ochiq — baholarni tahrirlash mumkin") }}
                    @endif
                </p>
                @if($group->is_locked)
                    <p class="text-xs text-gray-600 mt-0.5">
                        {{ __("Yopilgan") }}: {{ $group->locked_by_name }} · {{ $group->locked_at?->format('Y-m-d H:i') }}
                    </p>
                @endif
                @if($group->sent_to_test_markazi_at)
                    <p class="text-xs text-blue-700 mt-0.5">
                        ✓ {{ __("Test markaziga yuborilgan") }}: {{ $group->sent_to_test_markazi_at->format('Y-m-d H:i') }}
                    </p>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if($canEdit && !$group->is_locked)
                    <form method="POST" action="{{ route('admin.retake-journal.lock', $group->id) }}"
                          onsubmit="return confirm('{{ __("Guruhni yopish va yakuniy baholarni shakllantirishni tasdiqlaysizmi? Keyin tahrirlay olmaysiz.") }}')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-amber-600 text-white rounded hover:bg-amber-700">
                            🔒 {{ __("Yakuniy yuborish (yopish)") }}
                        </button>
                    </form>
                @endif

                @if($group->is_locked)
                    <a href="{{ route('admin.retake-journal.vedomost', $group->id) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-blue-600 text-white rounded hover:bg-blue-700">
                        📊 {{ __("Vedomost (PDF)") }}
                    </a>
                @endif

                @if($canEdit && $group->is_locked && !$group->sent_to_test_markazi_at && in_array($group->assessment_type, ['oske', 'test', 'oske_test'], true))
                    <form method="POST" action="{{ route('admin.retake-journal.send-to-test-markazi', $group->id) }}"
                          onsubmit="return confirm('{{ __("Test markaziga yuborishni tasdiqlaysizmi?") }}')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded hover:bg-green-700">
                            ✉️ {{ __("Test markaziga yuborish") }}
                        </button>
                    </form>
                @endif

                @if($group->is_locked && auth()->user()?->hasAnyRole(['superadmin', 'admin']))
                    <form method="POST" action="{{ route('admin.retake-journal.unlock', $group->id) }}"
                          onsubmit="return confirm('{{ __("Lock'ni bekor qilishni tasdiqlaysizmi?") }}')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            🔓 {{ __("Ochish") }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 mb-4 border-b border-gray-200">
            <button type="button"
                    @click="tab = 'amaliyot'"
                    :class="tab === 'amaliyot' ? 'bg-white text-blue-700 border-blue-500' : 'text-gray-600 hover:text-gray-900 border-transparent'"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition">
                {{ __("Amaliyot") }}
            </button>
            <button type="button"
                    @click="tab = 'mustaqil'"
                    :class="tab === 'mustaqil' ? 'bg-white text-blue-700 border-blue-500' : 'text-gray-600 hover:text-gray-900 border-transparent'"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition">
                {{ __("Mustaqil ta'lim") }}
            </button>
        </div>

        {{-- AMALIYOT — kunlik baholar jadvali --}}
        <div x-show="tab === 'amaliyot'">
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
                                    <tr class="hover:bg-gray-50" data-app-row="{{ $app->id }}">
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
                                            <td class="px-0 py-0 text-center border-r border-gray-200"
                                                data-app-id="{{ $app->id }}"
                                                data-date="{{ $d }}">
                                                <input type="text"
                                                       inputmode="numeric"
                                                       maxlength="3"
                                                       value="{{ $val !== null ? rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.') : '' }}"
                                                       data-grade-input="{{ $app->id }}"
                                                       @if(!$canEdit) readonly @endif
                                                       @change="saveCell($event, {{ $app->id }}, '{{ $d }}')"
                                                       class="w-full px-1 py-1.5 text-xs text-center bg-transparent border-0 focus:ring-2 focus:ring-blue-300 focus:bg-white outline-none {{ $canEdit ? '' : 'cursor-default' }}"
                                                       style="{{ $val === null ? '' : ($val < 60 ? 'color:#b91c1c;font-weight:600;' : ($val < 75 ? 'color:#b45309;' : 'color:#15803d;font-weight:600;')) }}">
                                            </td>
                                        @endforeach
                                        <td class="px-2 py-1.5 text-center bg-blue-50 font-semibold {{ $avg !== null && $avg < 60 ? 'text-red-700' : 'text-blue-700' }}"
                                            data-avg-cell="{{ $app->id }}">
                                            {{ $avg !== null ? $avg : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if($canEdit)
                <div class="mt-3 text-xs text-gray-500">
                    💡 {{ __("Bahoni katakka kiritib, boshqa joyga bosing — avtomatik saqlanadi. 0 dan 100 gacha qiymat. O'rtacha avtomatik yangilanadi.") }}
                </div>
            @endif

            @if(!$isEditable)
                <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900">
                    ⚠️ {{ __("Bu guruh muddati tugagan. Baholarni faqat super-admin tahrirlay oladi.") }}
                </div>
            @endif
        </div>

        {{-- MUSTAQIL TA'LIM — talaba fayli + o'qituvchi bahosi --}}
        <div x-show="tab === 'mustaqil'" x-cloak>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                @if(count($applications) === 0)
                    <div class="p-10 text-center text-sm text-gray-500">
                        {{ __("Bu guruhda talabalar yo'q") }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">T/R</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("F.I.Sh") }}</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("Fayl") }}</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("Talaba izohi") }}</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("Yuklangan") }}</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase">{{ __("Baho") }}</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("O'qituvchi izohi") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($applications as $i => $app)
                                    @php
                                        $student = $app->group->student ?? null;
                                        $sub = $mustaqilMap[$app->id] ?? null;
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 text-gray-600">{{ $i + 1 }}</td>
                                        <td class="px-3 py-2">
                                            <span class="font-medium text-gray-900">{{ $student?->full_name ?? '—' }}</span>
                                            <span class="block text-[10px] text-gray-500">{{ $app->student_hemis_id }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($sub && $sub->file_path)
                                                <a href="{{ route('admin.retake-journal.mustaqil-download', [$group->id, $sub->id]) }}"
                                                   class="text-blue-600 hover:underline text-xs">
                                                    📎 {{ $sub->original_filename ?? __("Fayl") }}
                                                </a>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-700 text-[11px] max-w-xs">{{ $sub?->student_comment ?? '' }}</td>
                                        <td class="px-3 py-2 text-gray-500 text-[11px]">
                                            {{ $sub?->submitted_at ? $sub->submitted_at->format('Y-m-d H:i') : '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($sub && $sub->file_path)
                                                <input type="text"
                                                       inputmode="numeric"
                                                       maxlength="3"
                                                       value="{{ $sub->grade !== null ? rtrim(rtrim(number_format($sub->grade, 2, '.', ''), '0'), '.') : '' }}"
                                                       @if(!$canEdit) readonly @endif
                                                       @change="saveMustaqilCell($event, {{ $app->id }})"
                                                       data-mustaqil-grade="{{ $app->id }}"
                                                       class="w-16 px-2 py-1 text-xs text-center border border-gray-300 rounded focus:ring-2 focus:ring-blue-300 outline-none"
                                                       placeholder="—">
                                            @else
                                                <span class="text-gray-400 text-xs">{{ __("kutilmoqda") }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($sub && $sub->file_path)
                                                <input type="text"
                                                       value="{{ $sub->teacher_comment ?? '' }}"
                                                       @if(!$canEdit) readonly @endif
                                                       @change="saveMustaqilComment($event, {{ $app->id }})"
                                                       data-mustaqil-comment="{{ $app->id }}"
                                                       placeholder="{{ __('Izoh') }}"
                                                       class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-2 focus:ring-blue-300 outline-none">
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if($canEdit)
                <div class="mt-3 text-xs text-gray-500">
                    💡 {{ __("Talaba fayl yuklamaguncha baho qo'yib bo'lmaydi. Baho va izohni katakka kiritib boshqa joyga bosing — avtomatik saqlanadi.") }}
                </div>
            @endif
        </div>

    </div>

    @push('scripts')
        <script>
            function retakeJournal({ saveUrl, gradeMustaqilUrl, csrf, canEdit, tab }) {
                return {
                    saveUrl, gradeMustaqilUrl, csrf, canEdit, tab,
                    saving: {},

                    async saveCell(e, appId, date) {
                        if (!this.canEdit) return;
                        const input = e.target;
                        const raw = (input.value || '').trim();
                        const grade = raw === '' ? null : Number(raw);

                        if (grade !== null && (isNaN(grade) || grade < 0 || grade > 100)) {
                            input.classList.add('bg-red-50');
                            alert("Baho 0 dan 100 gacha bo'lishi kerak");
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

                                const v = data.grade !== null ? Number(data.grade) : null;
                                input.style.color = v === null ? '' : (v < 60 ? '#b91c1c' : (v < 75 ? '#b45309' : '#15803d'));
                                input.style.fontWeight = (v !== null && (v < 60 || v >= 75)) ? '600' : '';

                                // O'rtachani yangilash
                                this.recomputeAverage(appId);
                            }
                        } catch (err) {
                            alert('Tarmoq xatosi');
                            input.classList.add('bg-red-50');
                        } finally {
                            delete this.saving[key];
                        }
                    },

                    recomputeAverage(appId) {
                        const inputs = document.querySelectorAll(`input[data-grade-input="${appId}"]`);
                        const values = [];
                        inputs.forEach(inp => {
                            const raw = (inp.value || '').trim();
                            if (raw !== '' && !isNaN(Number(raw))) {
                                values.push(Number(raw));
                            }
                        });

                        const cell = document.querySelector(`td[data-avg-cell="${appId}"]`);
                        if (!cell) return;

                        if (values.length === 0) {
                            cell.textContent = '—';
                            cell.classList.remove('text-red-700');
                            cell.classList.add('text-blue-700');
                            return;
                        }

                        const avg = Math.round((values.reduce((a, b) => a + b, 0) / values.length) * 10) / 10;
                        cell.textContent = avg;
                        if (avg < 60) {
                            cell.classList.remove('text-blue-700');
                            cell.classList.add('text-red-700');
                        } else {
                            cell.classList.remove('text-red-700');
                            cell.classList.add('text-blue-700');
                        }
                    },

                    async saveMustaqilCell(e, appId) {
                        if (!this.canEdit) return;
                        const input = e.target;
                        const raw = (input.value || '').trim();
                        const grade = raw === '' ? null : Number(raw);

                        if (grade !== null && (isNaN(grade) || grade < 0 || grade > 100)) {
                            input.classList.add('bg-red-50');
                            alert("Baho 0 dan 100 gacha bo'lishi kerak");
                            return;
                        }

                        const commentInp = document.querySelector(`input[data-mustaqil-comment="${appId}"]`);
                        await this.saveMustaqil(appId, grade, commentInp ? commentInp.value : null, input);
                    },

                    async saveMustaqilComment(e, appId) {
                        if (!this.canEdit) return;
                        const input = e.target;
                        const gradeInp = document.querySelector(`input[data-mustaqil-grade="${appId}"]`);
                        const raw = gradeInp ? (gradeInp.value || '').trim() : '';
                        const grade = raw === '' ? null : Number(raw);
                        await this.saveMustaqil(appId, grade, input.value, input);
                    },

                    async saveMustaqil(appId, grade, comment, input) {
                        const key = `m|${appId}`;
                        if (this.saving[key]) return;
                        this.saving[key] = true;

                        try {
                            const res = await fetch(this.gradeMustaqilUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    application_id: appId,
                                    grade: grade,
                                    comment: comment,
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
