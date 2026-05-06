<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish — O'qituvchi va fan statistikasi") }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="{ openTeacher: null, openSubject: {} }">

        @php
            $teacherSearchHtml = '<div class="rf-item" style="min-width:220px;flex:1;">'
                . '<label class="rf-label"><span class="rf-dot" style="background:#0ea5e9;"></span> ' . __("O'qituvchi qidirish") . '</label>'
                . '<input type="text" name="teacher_search" value="' . e($filters['teacher_search'] ?? '') . '" placeholder="' . __('F.I.Sh. yoki qismi') . '" class="rf-input" style="width:100%;">'
                . '</div>';
        @endphp

        @include('partials._retake_filters', [
            'formAction' => route('admin.retake-teacher-subjects.index'),
            'educationTypes' => $educationTypes ?? collect(),
            'subjects' => $subjects ?? collect(),
            'hiddenFilters' => ['full_name', 'per_page'],
            'extraRow' => $teacherSearchHtml,
        ])

        {{-- Yuqori statistika --}}
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("O'qituvchilar soni") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalTeachers) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-violet-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Fanlar soni") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalSubjects) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Jami biriktirilgan talabalar") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalStudents) }}</p>
            </div>
        </div>

        @if(empty($tree))
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
                <p class="text-sm text-gray-500">{{ __("Tanlangan filtr bo'yicha biriktirilgan talabalar topilmadi") }}</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($tree as $teacherKey => $t)
                    @php
                        $tk = (string) $teacherKey;
                        $studentCountForTeacher = $t['total_students'];
                        $subjectsCount = count($t['subjects']);
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <button type="button"
                                @click="openTeacher = (openTeacher === '{{ $tk }}' ? null : '{{ $tk }}')"
                                class="w-full px-5 py-3 flex items-center justify-between gap-3 hover:bg-gray-50 transition">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold flex-shrink-0">
                                    {{ mb_strtoupper(mb_substr($t['teacher_name'] ?: '?', 0, 1)) }}
                                </div>
                                <div class="text-left min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 truncate">{{ $t['teacher_name'] }}</div>
                                    <div class="text-[11px] text-gray-500 mt-0.5">
                                        {{ $subjectsCount }} {{ __('ta fan') }} · {{ $studentCountForTeacher }} {{ __('ta talaba') }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">{{ $studentCountForTeacher }}</span>
                                <svg class="w-4 h-4 text-gray-400 transition" :class="openTeacher === '{{ $tk }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </button>

                        <div x-show="openTeacher === '{{ $tk }}'" x-cloak class="border-t border-gray-100 bg-gray-50/50 divide-y divide-gray-100">
                            @foreach($t['subjects'] as $subjectKey => $s)
                                @php $sk = $tk . '_' . $subjectKey; @endphp
                                <div class="px-5 py-3">
                                    <button type="button"
                                            @click="openSubject['{{ $sk }}'] = !openSubject['{{ $sk }}']"
                                            class="w-full flex items-center justify-between gap-3 text-left">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-2 h-2 rounded-full bg-violet-500 flex-shrink-0"></span>
                                            <span class="text-sm font-semibold text-gray-800 truncate">{{ $s['subject_name'] ?: '—' }}</span>
                                            @if($s['group_name'])
                                                <span class="text-[10px] text-gray-500 truncate">· {{ $s['group_name'] }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-[11px] font-bold rounded-full">{{ count($s['students']) }} {{ __('talaba') }}</span>
                                            <svg class="w-3.5 h-3.5 text-gray-400 transition" :class="openSubject['{{ $sk }}'] ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </button>

                                    <div x-show="openSubject['{{ $sk }}']" x-cloak class="mt-3">
                                        <div class="overflow-x-auto bg-white rounded-lg border border-gray-200">
                                            <table class="min-w-full text-xs">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase tracking-wide" style="width:36px;">№</th>
                                                        <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase tracking-wide">{{ __("F.I.Sh.") }}</th>
                                                        <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase tracking-wide" style="width:120px;">HEMIS ID</th>
                                                        <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase tracking-wide">{{ __("Fakultet") }}</th>
                                                        <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase tracking-wide">{{ __("Yo'nalish") }}</th>
                                                        <th class="px-3 py-2 text-left font-semibold text-gray-600 uppercase tracking-wide" style="width:90px;">{{ __("Kurs") }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                @foreach($s['students'] as $i => $st)
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                                                        <td class="px-3 py-2 font-medium text-gray-900">{{ $st['full_name'] ?: '—' }}</td>
                                                        <td class="px-3 py-2 text-gray-600 font-mono">{{ $st['hemis_id'] }}</td>
                                                        <td class="px-3 py-2 text-gray-700">{{ $st['department_name'] ?: '—' }}</td>
                                                        <td class="px-3 py-2 text-gray-700">{{ $st['specialty_name'] ?: '—' }}</td>
                                                        <td class="px-3 py-2 text-gray-700">{{ $st['level_name'] ?: '—' }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>

    @push('styles')
    <style>
        [x-cloak] { display: none !important; }
        .rf-input {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 13px;
            background: #fff;
        }
        .rf-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.2);
        }
    </style>
    @endpush
</x-teacher-app-layout>
