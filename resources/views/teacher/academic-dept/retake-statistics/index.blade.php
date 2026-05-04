<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish — Statistika va eksport") }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">

        @php
            $extraRowHtml = view('teacher.academic-dept.retake-statistics._filter_extras', [
                'filters' => $filters,
            ])->render();
            $exportButtonHtml = '<a href="' . route('admin.retake-statistics.export', request()->query()) . '" class="rf-btn" style="background:#16a34a;">📊 ' . __("Excel'ga eksport") . '</a>';
        @endphp

        {{-- Yagona filtr formasi: cascade + holat + sanalar + eksport --}}
        @include('partials._retake_filters', [
            'formAction' => route('admin.retake-statistics.index'),
            'educationTypes' => $educationTypes ?? collect(),
            'subjects' => $subjects ?? collect(),
            'hiddenFilters' => ['full_name'],
            'extraRow' => $extraRowHtml,
            'extraButton' => $exportButtonHtml,
        ])

        {{-- Umumiy ko'rsatkichlar --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __("Jami") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalApplications) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Kutilmoqda") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($statusStats['pending']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Tasdiqlangan") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($statusStats['approved']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Rad etilgan") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($statusStats['rejected']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Jami summa (UZS)") }}</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($totalAmount, 0, '.', ' ') }}</p>
            </div>
        </div>

        {{-- Bosqich bo'yicha (kutilayotgan arizalar uchun) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __("Kutilayotgan arizalar bosqich kesimida") }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="border border-gray-200 rounded-lg p-3">
                    <p class="text-xs text-gray-500">{{ __("Dekan kutmoqda") }}</p>
                    <p class="text-xl font-bold text-yellow-700">{{ number_format($stageStats['dean_pending']) }}</p>
                </div>
                <div class="border border-gray-200 rounded-lg p-3">
                    <p class="text-xs text-gray-500">{{ __("Registrator kutmoqda") }}</p>
                    <p class="text-xl font-bold text-yellow-700">{{ number_format($stageStats['registrar_pending']) }}</p>
                </div>
                <div class="border border-gray-200 rounded-lg p-3">
                    <p class="text-xs text-gray-500">{{ __("O'quv bo'limi kutmoqda") }}</p>
                    <p class="text-xl font-bold text-blue-700">{{ number_format($stageStats['academic_pending']) }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Top fanlar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __("Eng ko'p arizali fanlar") }}</h3>
                </div>
                @if($topSubjects->count() === 0)
                    <p class="p-6 text-center text-gray-500 text-sm">{{ __("Ma'lumot yo'q") }}</p>
                @else
                    <div class="divide-y divide-gray-100">
                        @php $maxTotal = $topSubjects->max('total'); @endphp
                        @foreach($topSubjects as $s)
                            <div class="px-5 py-2.5">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-800">
                                        {{ $s->subject_name }}
                                        <span class="text-[11px] text-gray-500">· {{ $s->semester_name }}</span>
                                    </span>
                                    <span class="text-sm font-semibold text-gray-900">{{ $s->total }}</span>
                                </div>
                                <div class="h-1.5 rounded bg-gray-100 overflow-hidden">
                                    <div class="h-full bg-blue-500 rounded" style="width: {{ ($s->total / $maxTotal * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Fakultet kesimi --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __("Fakultet kesimida") }}</h3>
                </div>
                @if($departmentStats->count() === 0)
                    <p class="p-6 text-center text-gray-500 text-sm">{{ __("Ma'lumot yo'q") }}</p>
                @else
                    <div class="divide-y divide-gray-100">
                        @php $maxDep = $departmentStats->max('total'); @endphp
                        @foreach($departmentStats as $d)
                            <div class="px-5 py-2.5">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-800">{{ $d->department_name ?? '—' }}</span>
                                    <span class="text-sm font-semibold text-gray-900">{{ $d->total }}</span>
                                </div>
                                <div class="h-1.5 rounded bg-gray-100 overflow-hidden">
                                    <div class="h-full bg-purple-500 rounded" style="width: {{ ($d->total / $maxDep * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-teacher-app-layout>
