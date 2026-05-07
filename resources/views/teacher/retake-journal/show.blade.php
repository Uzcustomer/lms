<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.retake-journal.index') }}" class="text-sm text-blue-600 hover:underline">
                ← {{ __("Jurnal ro'yxati") }}
            </a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $group->name }}</h2>
        </div>
    </x-slot>

    @php
        $defaultWeights = app(\App\Services\Retake\RetakeJournalService::class)->defaultWeights($group);
    @endphp
    <div class="py-4 px-3 sm:px-4 lg:px-6 w-full"
         x-data="retakeJournal({
             saveJoriyUrl: '{{ route('admin.retake-journal.save-joriy', $group->id) }}',
             gradeMustaqilUrl: '{{ route('admin.retake-journal.mustaqil-grade', $group->id) }}',
             vedomostUrl: '{{ route('admin.retake-journal.vedomost', $group->id) }}',
             csrf: '{{ csrf_token() }}',
             canEdit: @js($canEdit),
             tab: 'amaliyot',
             filterMode: 'batafsil',
             weightsModalOpen: false,
             weights: @js($defaultWeights),
             generating: false,
         })">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-3 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Status banner --}}
        @if($group->is_locked)
            <div class="bg-amber-50 border border-amber-300 rounded-lg p-3 mb-3 flex items-start gap-2 text-sm text-amber-900">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <div>
                    <strong>{{ __("Baholar qulflangan!") }}</strong>
                    {{ __("Yakuniy qilingan. Baholarni o'zgartirish mumkin emas.") }}
                    @if($group->locked_by_name)
                        <span class="block text-xs text-amber-700 mt-0.5">{{ $group->locked_by_name }} · {{ $group->locked_at?->format('d.m.Y H:i') }}</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Asosiy + sidebar layout --}}
        <div class="flex flex-col lg:flex-row gap-3">

            {{-- ASOSIY KONTENT --}}
            <div class="flex-1 min-w-0 order-2 lg:order-1">

                {{-- Tabs --}}
                <div class="flex gap-1 mb-3 border-b border-gray-200">
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

                {{-- AMALIYOT JADVALI --}}
                <div x-show="tab === 'amaliyot'">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        @if(count($applications) === 0)
                            <div class="p-10 text-center text-sm text-gray-500">
                                {{ __("Bu guruhda talabalar yo'q") }}
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-xs border-collapse rj-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-2 py-2 text-center font-semibold text-gray-600 border-r border-gray-200" style="min-width:36px;">T/R</th>
                                            <th class="px-2 py-2 text-left font-semibold text-gray-600 border-r border-gray-200" style="min-width:240px;">F.I.SH.</th>
                                            <th class="px-3 py-2 text-center font-semibold text-blue-800 bg-blue-50" style="min-width:120px;">
                                                {{ __("Joriy nazorat (JN)") }}
                                                <div class="text-[9px] font-normal text-gray-500 mt-0.5">{{ __("yagona baho") }}</div>
                                            </th>
                                            <th class="px-2 py-2 text-left font-semibold text-gray-600" style="min-width:160px;">{{ __("Qachon qo'yilgan") }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                    @foreach($applications as $i => $app)
                                        @php
                                            $student = $app->group->student ?? null;
                                            $val = $app->joriy_score;
                                            $attempt = $attemptsMap[$app->id] ?? 1;
                                            $cellStyle = '';
                                            if ($val !== null) {
                                                $f = (float) $val;
                                                if ($f < 60)        $cellStyle = 'color:#b91c1c;font-weight:700;';
                                                elseif ($f < 75)    $cellStyle = 'color:#b45309;font-weight:600;';
                                                elseif ($f < 90)    $cellStyle = 'color:#15803d;font-weight:600;';
                                                else                $cellStyle = 'color:#0f5132;font-weight:700;';
                                            }
                                        @endphp
                                        <tr class="border-b border-gray-100 hover:bg-gray-50" data-app-row="{{ $app->id }}">
                                            <td class="px-2 py-1.5 text-center text-gray-600 border-r border-gray-200">{{ $i + 1 }}</td>
                                            <td class="px-2 py-1.5 border-r border-gray-200">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-gray-900 font-medium truncate" style="max-width:170px;">{{ $student?->full_name ?? '—' }}</span>
                                                    @if($attempt === 1)
                                                        <span class="rj-badge rj-badge-1">1-URINISH ✓</span>
                                                    @else
                                                        <span class="rj-badge rj-badge-{{ min($attempt, 3) }}">{{ $attempt }}-URINISH</span>
                                                    @endif
                                                </div>
                                                <span class="block text-[10px] text-gray-500">{{ $app->student_hemis_id }}</span>
                                            </td>
                                            <td class="px-2 py-1 text-center bg-blue-50">
                                                <input type="text"
                                                       inputmode="numeric"
                                                       maxlength="3"
                                                       value="{{ $val !== null ? rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.') : '' }}"
                                                       data-joriy-input="{{ $app->id }}"
                                                       @if(!$canEdit) readonly @endif
                                                       @change="saveJoriy($event, {{ $app->id }})"
                                                       placeholder="—"
                                                       class="w-20 px-2 py-1.5 text-center text-base font-bold bg-white border border-blue-200 rounded focus:ring-2 focus:ring-blue-400 focus:border-blue-500 outline-none {{ $canEdit ? '' : 'cursor-default bg-gray-50' }}"
                                                       style="{{ $cellStyle }}">
                                            </td>
                                            <td class="px-2 py-1.5 text-[11px] text-gray-500">
                                                @if($app->joriy_graded_at)
                                                    {{ $app->joriy_graded_at->format('d.m.Y H:i') }}
                                                    <span class="block text-[10px] text-gray-400">{{ $app->joriy_graded_by_name }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if($canEdit && !$group->is_locked)
                        <div class="mt-2 text-[11px] text-gray-500">
                            💡 {{ __("JN bahosini katakka kiritib, boshqa joyga bosing — avtomatik saqlanadi. Oyna yopilish kunigacha tahrirlash mumkin.") }}
                        </div>
                    @endif

                    {{-- YN ga yuborish (Yakuniy qilish) paneli --}}
                    @if($canEdit && !$group->is_locked && count($applications) > 0)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-3 p-4">
                            <div class="flex items-start justify-between flex-wrap gap-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900">{{ __("Yakuniy nazorat (YN) ga yuborish") }}</h4>
                                    <p class="text-[11px] text-gray-500 mt-1">
                                        {{ __("Barcha kunlik baholar va mustaqil ta'lim baholari qulflanadi. Keyin tahrirlash mumkin emas.") }}
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('admin.retake-journal.lock', $group->id) }}"
                                      onsubmit="return confirm('{{ __("Yakuniy yuborishni tasdiqlaysizmi? Keyin tahrirlash mumkin emas.") }}')">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-amber-600 rounded-lg hover:bg-amber-700">
                                        🔒 {{ __("Yakuniy qilish") }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- OSKI / Test natijalari paneli --}}
                    @if(in_array($group->assessment_type, ['oske', 'test', 'oske_test'], true))
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-3 p-4">
                            <div class="flex items-start justify-between flex-wrap gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-900">{{ __("OSKI va Test natijalari") }}</h4>
                                        <div class="text-xs text-gray-700 mt-1 space-y-0.5">
                                            @if($group->oske_date)
                                                <div>
                                                    OSKI: <span class="font-medium">{{ $group->oske_date->format('d.m.Y') }}</span>
                                                    @php $oskeFinished = $group->oske_date->lt(now()->startOfDay()); @endphp
                                                    @if($oskeFinished)
                                                        <span class="text-green-700">({{ __("O'tgan") }})</span>
                                                    @else
                                                        <span class="text-amber-700">({{ __("Kelmoqda") }})</span>
                                                    @endif
                                                </div>
                                            @endif
                                            @if($group->test_date)
                                                <div>
                                                    Test: <span class="font-medium">{{ $group->test_date->format('d.m.Y') }}</span>
                                                    @php $testFinished = $group->test_date->lt(now()->startOfDay()); @endphp
                                                    @if($testFinished)
                                                        <span class="text-green-700">({{ __("O'tgan") }})</span>
                                                    @else
                                                        <span class="text-amber-700">({{ __("Kelmoqda") }})</span>
                                                    @endif
                                                </div>
                                            @endif
                                            @if($group->sent_to_test_markazi_at)
                                                <div class="text-blue-700">
                                                    ✉️ {{ __("Test markaziga yuborilgan") }}: {{ $group->sent_to_test_markazi_at->format('d.m.Y H:i') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 flex-wrap">
                                    @if($canEdit && !$group->sent_to_test_markazi_at)
                                        <form method="POST" action="{{ route('admin.retake-journal.fetch-results', $group->id) }}"
                                              onsubmit="return confirm('{{ __("HEMIS'dan OSKE va Test natijalarini tortishni tasdiqlaysizmi?") }}')">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                                                📥 {{ __("Natijalarni tortish") }}
                                            </button>
                                        </form>
                                    @endif
                                    @if($group->is_locked)
                                        <button type="button"
                                                @click="weightsModalOpen = true"
                                                :disabled="generating"
                                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-60">
                                            📊 <span x-text="generating ? '{{ __("Yaratilmoqda...") }}' : '{{ __("YN qaydnoma yaratish") }}'"></span>
                                        </button>
                                    @endif
                                    @if($canEdit && $group->is_locked && !$group->sent_to_test_markazi_at)
                                        <form method="POST" action="{{ route('admin.retake-journal.send-to-test-markazi', $group->id) }}"
                                              onsubmit="return confirm('{{ __("Test markaziga yuborishni tasdiqlaysizmi?") }}')">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                                ✉️ {{ __("Test markaziga yuborish") }}
                                            </button>
                                        </form>
                                    @endif
                                    @if($group->is_locked && auth()->user()?->hasAnyRole(['superadmin', 'admin']))
                                        <form method="POST" action="{{ route('admin.retake-journal.unlock', $group->id) }}"
                                              onsubmit="return confirm('{{ __("Lock'ni bekor qilishni tasdiqlaysizmi?") }}')">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                                                🔓 {{ __("Ochish") }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @elseif($group->is_locked)
                        {{-- Sinov fan uchun ham vedomost yaratiladi --}}
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-3 p-4 flex items-center justify-between flex-wrap gap-3">
                            <p class="text-sm text-gray-700">{{ __("Sinov fan — vedomost tayyor") }}</p>
                            <button type="button"
                                    @click="weightsModalOpen = true"
                                    :disabled="generating"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-60">
                                📊 <span x-text="generating ? '{{ __("Yaratilmoqda...") }}' : '{{ __("YN qaydnoma yaratish") }}'"></span>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- MUSTAQIL TA'LIM --}}
                <div x-show="tab === 'mustaqil'" x-cloak>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        @if(count($applications) === 0)
                            <div class="p-10 text-center text-sm text-gray-500">{{ __("Bu guruhda talabalar yo'q") }}</div>
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
                                                {{ $sub?->submitted_at ? $sub->submitted_at->format('d.m.Y H:i') : '—' }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                @if($sub && $sub->file_path)
                                                    <input type="text"
                                                           inputmode="numeric"
                                                           maxlength="3"
                                                           value="{{ $sub->grade !== null ? rtrim(rtrim(number_format($sub->grade, 2, '.', ''), '0'), '.') : '' }}"
                                                           @if(!$canEdit || $group->is_locked) readonly @endif
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
                                                           @if(!$canEdit || $group->is_locked) readonly @endif
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

                    @if($canEdit && !$group->is_locked)
                        <div class="mt-2 text-[11px] text-gray-500">
                            💡 {{ __("Talaba fayl yuklamaguncha baho qo'yib bo'lmaydi.") }}
                        </div>
                    @endif
                </div>

            </div>

            {{-- O'NG SIDEBAR — INFO --}}
            <aside class="w-full lg:w-80 flex-shrink-0 order-1 lg:order-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 sticky top-3 overflow-hidden">

                    {{-- Header: Group title + toggle --}}
                    <div class="px-4 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-100">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-[10px] uppercase font-semibold text-blue-700 tracking-wider">{{ __("Qayta o'qish guruhi") }}</div>
                                <div class="text-sm font-semibold text-gray-900 truncate mt-0.5">{{ $group->subject_name }}</div>
                                <div class="text-[11px] text-gray-600 truncate">{{ $group->name }}</div>
                            </div>
                            <div class="inline-flex bg-white rounded-lg shadow-sm border border-gray-200 p-0.5">
                                <button type="button" @click="filterMode = 'ixcham'"
                                        :class="filterMode === 'ixcham' ? 'bg-blue-600 text-white shadow' : 'text-gray-600 hover:text-gray-900'"
                                        class="px-2.5 py-1 text-[10px] font-semibold rounded transition">{{ __("Ixcham") }}</button>
                                <button type="button" @click="filterMode = 'batafsil'"
                                        :class="filterMode === 'batafsil' ? 'bg-blue-600 text-white shadow' : 'text-gray-600 hover:text-gray-900'"
                                        class="px-2.5 py-1 text-[10px] font-semibold rounded transition">{{ __("Batafsil") }}</button>
                            </div>
                        </div>
                    </div>

                    {{-- Stat strip: students count + assessment type --}}
                    <div class="grid grid-cols-2 divide-x divide-gray-100 border-b border-gray-100">
                        <div class="px-3 py-2 text-center">
                            <div class="text-lg font-bold text-gray-900 leading-none">{{ count($applications) }}</div>
                            <div class="text-[9px] uppercase tracking-wider text-gray-500 mt-1">{{ __("Talabalar") }}</div>
                        </div>
                        <div class="px-3 py-2 text-center">
                            @php
                                $atypeLabels = [
                                    'oske' => 'OSKI',
                                    'test' => 'TEST',
                                    'oske_test' => 'OSKI+TEST',
                                    'sinov_fan' => __("Sinov fan"),
                                ];
                            @endphp
                            <div class="text-sm font-bold text-rose-700 leading-none">{{ $atypeLabels[$group->assessment_type] ?? '—' }}</div>
                            <div class="text-[9px] uppercase tracking-wider text-gray-500 mt-1">{{ __("Baholash") }}</div>
                        </div>
                    </div>

                    {{-- O'qituvchi (always visible) --}}
                    <div class="px-3 py-2.5 border-b border-gray-100">
                        <div class="rj-teacher-block flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0 font-bold text-xs">
                                {{ strtoupper(mb_substr($group->teacher_name ?? '?', 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[9px] uppercase font-semibold text-emerald-700 tracking-wider">{{ __("Amaliyot o'qituvchisi") }}</div>
                                <div class="text-xs text-gray-900 font-medium truncate">{{ $group->teacher_name ?? '—' }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Compact: just Fakultet + Davr --}}
                    <div class="px-3 py-2.5 space-y-2">
                        @if($facultyNames->isNotEmpty())
                            <div class="rj-row">
                                <span class="rj-row-icon bg-emerald-100 text-emerald-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M3 7h18M5 21V7M19 21V7M9 21V11h6v10"/></svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="rj-row-label">{{ __("Fakultet") }}</div>
                                    <div class="rj-row-value">
                                        {{ $facultyNames->count() === 1 ? $facultyNames->first() : $facultyNames->count() . ' ' . __('ta turli') }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="rj-row">
                            <span class="rj-row-icon bg-amber-100 text-amber-700">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="rj-row-label">{{ __("Davr") }}</div>
                                <div class="rj-row-value">
                                    {{ $group->start_date->format('d.m.Y') }} → {{ $group->end_date->format('d.m.Y') }}
                                    <span class="text-[10px] text-gray-500 ml-1">({{ $group->start_date->diffInDays($group->end_date) + 1 }} {{ __("kun") }})</span>
                                </div>
                            </div>
                        </div>

                        {{-- Batafsil — qo'shimcha qatorlar --}}
                        <div x-show="filterMode === 'batafsil'" x-cloak class="space-y-2">

                            @if($specialtyNames->isNotEmpty())
                                <div class="rj-row">
                                    <span class="rj-row-icon bg-cyan-100 text-cyan-700">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="rj-row-label">{{ __("Yo'nalish") }}</div>
                                        <div class="rj-row-value">
                                            {{ $specialtyNames->count() === 1 ? $specialtyNames->first() : $specialtyNames->count() . ' ' . __('ta turli') }}
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-2">
                                @if($levelNames->isNotEmpty())
                                    <div class="rj-pill bg-violet-50 border-violet-200">
                                        <div class="rj-pill-label text-violet-700">{{ __("Kurs") }}</div>
                                        <div class="rj-pill-value">{{ $levelNames->implode(', ') }}</div>
                                    </div>
                                @endif
                                @if($semesterNames->isNotEmpty())
                                    <div class="rj-pill bg-teal-50 border-teal-200">
                                        <div class="rj-pill-label text-teal-700">{{ __("Semestr") }}</div>
                                        <div class="rj-pill-value">{{ $semesterNames->implode(', ') }}</div>
                                    </div>
                                @endif
                            </div>

                            @if($groupNames->isNotEmpty())
                                <div class="rj-row">
                                    <span class="rj-row-icon bg-indigo-100 text-indigo-700">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="rj-row-label">{{ __("Talabalar guruhlari") }}</div>
                                        <div class="rj-row-value">
                                            {{ $groupNames->count() <= 3 ? $groupNames->implode(', ') : $groupNames->count() . ' ' . __('ta turli') }}
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-center justify-between pt-1.5 border-t border-gray-100 text-[10px] text-gray-500">
                                <span>{{ __("Guruh ID") }}: <span class="font-mono text-gray-700">#{{ $group->id }}</span></span>
                                @if($group->is_locked)
                                    <span class="text-amber-700 font-semibold">🔒 {{ __("Qulflangan") }}</span>
                                @else
                                    <span class="text-green-700 font-semibold">● {{ __("Faol") }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

        </div>

        {{-- YN qaydnoma vazn taqsimlash modali --}}
        <div x-show="weightsModalOpen" x-cloak class="fixed inset-0 z-50">
            <div class="fixed inset-0 bg-black/50" @click="weightsModalOpen = false"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-md relative" @click.stop>
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800">{{ __("Vaznlarni taqsimlang") }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ __("Jami 100 bo'lishi kerak") }}</p>
                    </div>
                    <div class="px-6 py-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 w-20">JN</label>
                            <input type="number" min="0" max="100" x-model.number="weights.jn"
                                   class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-center text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 w-20">MT</label>
                            <input type="number" min="0" max="100" x-model.number="weights.mt"
                                   class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-center text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 w-20">ON</label>
                            <input type="number" min="0" max="100" x-model.number="weights.on"
                                   class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-center text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 w-20">OSKI</label>
                            <input type="number" min="0" max="100" x-model.number="weights.oski"
                                   class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-center text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 w-20">Test</label>
                            <input type="number" min="0" max="100" x-model.number="weights.test"
                                   class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-center text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                            <span class="text-sm font-bold text-gray-800">{{ __("Jami") }}:</span>
                            <span class="text-lg font-bold"
                                  :class="(weights.jn + weights.mt + weights.on + weights.oski + weights.test) === 100 ? 'text-green-600' : 'text-red-600'"
                                  x-text="(weights.jn || 0) + (weights.mt || 0) + (weights.on || 0) + (weights.oski || 0) + (weights.test || 0)"></span>
                        </div>
                        <p class="text-xs text-red-600"
                           x-show="(weights.jn + weights.mt + weights.on + weights.oski + weights.test) !== 100">
                            {{ __("Jami 100 bo'lishi kerak!") }}
                        </p>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                        <button type="button" @click="weightsModalOpen = false"
                                class="px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition text-sm">
                            {{ __("Bekor qilish") }}
                        </button>
                        <button type="button"
                                @click="generateVedomost()"
                                :disabled="generating || (weights.jn + weights.mt + weights.on + weights.oski + weights.test) !== 100"
                                class="px-5 py-2.5 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition shadow-sm text-sm disabled:opacity-60">
                            <span x-text="generating ? '{{ __("Yaratilmoqda...") }}' : '{{ __("Yaratish") }}'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    @push('styles')
    <style>
        .rj-table input[readonly] { background: transparent; }
        .rj-table th { font-size: 10px; }
        .rj-table tbody td { padding-top: 4px; padding-bottom: 4px; }

        .rj-badge {
            display: inline-flex;
            align-items: center;
            padding: 1px 5px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
        }
        .rj-badge-1 { background: #dcfce7; color: #15803d; }
        .rj-badge-2 { background: #fef3c7; color: #92400e; }
        .rj-badge-3 { background: #fee2e2; color: #b91c1c; }

        .rj-field {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 6px 9px;
        }
        .rj-field-label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }
        .rj-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .rj-field-value { font-size: 12px; color: #1f2937; font-weight: 500; }
        .rj-section-label {
            font-size: 10px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding-top: 4px;
            border-top: 1px solid #e5e7eb;
        }
        .rj-teacher-block {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 6px 9px;
        }

        /* Sidebar info row */
        .rj-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .rj-row-icon {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .rj-row-label {
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }
        .rj-row-value {
            font-size: 12px;
            color: #1f2937;
            font-weight: 500;
            line-height: 1.35;
            word-break: break-word;
        }

        /* Pill cards */
        .rj-pill {
            border: 1px solid;
            border-radius: 8px;
            padding: 6px 8px;
            text-align: center;
        }
        .rj-pill-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .rj-pill-value {
            font-size: 13px;
            color: #1f2937;
            font-weight: 700;
            margin-top: 1px;
        }

        [x-cloak] { display: none !important; }
    </style>
    @endpush

    @push('scripts')
        <script>
            function retakeJournal({ saveJoriyUrl, gradeMustaqilUrl, vedomostUrl, csrf, canEdit, tab, filterMode, weightsModalOpen, weights, generating }) {
                return {
                    saveJoriyUrl, gradeMustaqilUrl, vedomostUrl, csrf, canEdit, tab, filterMode,
                    weightsModalOpen, weights, generating,
                    saving: {},

                    async generateVedomost() {
                        const total = (this.weights.jn || 0) + (this.weights.mt || 0)
                                    + (this.weights.on || 0) + (this.weights.oski || 0) + (this.weights.test || 0);
                        if (total !== 100) {
                            alert("Vaznlar jami 100 bo'lishi kerak!");
                            return;
                        }
                        if (this.generating) return;
                        this.generating = true;
                        try {
                            const res = await fetch(this.vedomostUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'Accept': 'application/octet-stream',
                                },
                                body: JSON.stringify({
                                    weight_jn: this.weights.jn,
                                    weight_mt: this.weights.mt,
                                    weight_on: this.weights.on,
                                    weight_oski: this.weights.oski,
                                    weight_test: this.weights.test,
                                }),
                            });
                            if (!res.ok) {
                                let msg = 'Server xatosi';
                                try {
                                    const j = await res.json();
                                    msg = j.error || j.message || msg;
                                } catch (_) {}
                                throw new Error(msg);
                            }
                            const cd = res.headers.get('Content-Disposition') || '';
                            let fileName = 'YN_qaydnoma.xlsx';
                            const m = cd.match(/filename="?([^";\n]+)"?/);
                            if (m && m[1]) fileName = m[1];
                            const blob = await res.blob();
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = fileName;
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            window.URL.revokeObjectURL(url);
                            this.weightsModalOpen = false;
                        } catch (err) {
                            alert('Xatolik: ' + err.message);
                        } finally {
                            this.generating = false;
                        }
                    },

                    async saveJoriy(e, appId) {
                        if (!this.canEdit) return;
                        const input = e.target;
                        const raw = (input.value || '').trim();
                        const score = raw === '' ? null : Number(raw);

                        if (score !== null && (isNaN(score) || score < 0 || score > 100)) {
                            input.classList.add('bg-red-50');
                            alert("Baho 0 dan 100 gacha bo'lishi kerak");
                            return;
                        }

                        const key = `j|${appId}`;
                        if (this.saving[key]) return;
                        this.saving[key] = true;

                        try {
                            const res = await fetch(this.saveJoriyUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    application_id: appId,
                                    score: score,
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

                                const v = data.score !== null ? Number(data.score) : null;
                                input.style.color = '';
                                input.style.fontWeight = '';
                                if (v !== null) {
                                    if (v < 60) { input.style.color = '#b91c1c'; input.style.fontWeight = '700'; }
                                    else if (v < 75) { input.style.color = '#b45309'; input.style.fontWeight = '600'; }
                                    else if (v < 90) { input.style.color = '#15803d'; input.style.fontWeight = '600'; }
                                    else { input.style.color = '#0f5132'; input.style.fontWeight = '700'; }
                                }
                            }
                        } catch (err) {
                            alert('Tarmoq xatosi');
                            input.classList.add('bg-red-50');
                        } finally {
                            delete this.saving[key];
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
