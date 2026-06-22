<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish - Test markazi") }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full bg-slate-50 min-h-screen">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="mb-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 bg-gradient-to-r from-slate-900 via-slate-800 to-blue-900 text-white">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-blue-100">{{ __("Qayta o'qish") }}</p>
                        <h3 class="text-lg font-bold mt-1">{{ __("Test markazi nazorati") }}</h3>
                        <p class="text-sm text-slate-200 mt-1">{{ __("Guruhlar va individual testga yuborilgan talabalarni alohida kuzating.") }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="rounded-xl bg-white/10 border border-white/15 px-4 py-2">
                            <span class="block text-[11px] text-blue-100">{{ __("Guruhlar") }}</span>
                            <span class="text-xl font-bold">{{ $groups->total() }}</span>
                        </div>
                        <div class="rounded-xl bg-white/10 border border-white/15 px-4 py-2">
                            <span class="block text-[11px] text-blue-100">{{ __("Talabalar") }}</span>
                            <span class="text-xl font-bold">{{ $sentApplications->total() }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white">
                <div class="inline-flex w-full sm:w-auto rounded-xl bg-slate-100 p-1 border border-slate-200">
                    <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'groups']) }}"
                       class="flex-1 sm:flex-none text-center px-4 py-2 rounded-lg text-sm font-semibold transition {{ $activeTab === 'groups' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        {{ __("Testga yuborilgan guruhlar") }}
                    </a>
                    <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'students']) }}"
                       class="flex-1 sm:flex-none text-center px-4 py-2 rounded-lg text-sm font-semibold transition {{ $activeTab === 'students' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        {{ __("Testga yuborilgan talabalar") }}
                    </a>
                </div>

                <div class="text-xs text-slate-500">
                    {{ $activeTab === 'groups' ? __("Guruh kesimida ko'rish") : __("Talaba kesimida ko'rish") }}
                </div>
            </div>
        </div>

        @if($activeTab === 'groups')
            <form method="POST" action="{{ route('admin.retake-test-markazi.generate-yn-oldi-word') }}" id="retake-yn-word-form">
                @csrf
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    @if($groups->isEmpty())
                        <div class="p-10 text-center text-sm text-gray-500">
                            {{ __("Hozircha test markaziga yuborilgan guruhlar yo'q") }}
                        </div>
                    @else
                        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 bg-white">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">{{ __("Testga yuborilgan guruhlar") }}</h4>
                                <p class="text-xs text-slate-500 mt-1">{{ __("Tanlangan guruhlar uchun YN oldi Word hosil qiling") }}</p>
                            </div>
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 shadow-sm">
                                {{ __("YN oldi word") }}
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead class="bg-slate-100/80">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">
                                        <input type="checkbox" id="select-all-retake-yn" class="rounded border-gray-300">
                                    </th>
                                    <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("Guruh") }}</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("Fan") }}</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("O'qituvchi") }}</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("Tur") }}</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("OSKE / TEST sanasi") }}</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-bold text-slate-500 uppercase">{{ __("Talabalar") }}</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("Yuborilgan") }}</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100">
                                @foreach($groups as $g)
                                    @php
                                        $atypeLabels = [
                                            'oske' => 'OSKE',
                                            'test' => 'TEST',
                                            'oske_test' => 'OSKE + TEST',
                                        ];
                                    @endphp
                                    <tr class="hover:bg-blue-50/40 transition">
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <input type="checkbox" name="group_ids[]" value="{{ $g->id }}" class="retake-yn-group-checkbox rounded border-gray-300">
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-950 font-bold">{{ $g->name }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-700">
                                            {{ $g->subject_name }}
                                            <span class="block text-[11px] text-slate-400 mt-0.5">{{ $g->semester_name }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-700">{{ $g->teacher_name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800">
                                                {{ $atypeLabels[$g->assessment_type] ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-700 leading-5">
                                            @if($g->oske_date)OSKE: {{ $g->oske_date->format('Y-m-d') }}@endif
                                            @if($g->oske_date && $g->test_date)<br>@endif
                                            @if($g->test_date)TEST: {{ $g->test_date->format('Y-m-d') }}@endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-900 text-right font-bold">{{ $g->students_count }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-500">
                                            {{ $g->sent_to_test_markazi_at?->format('Y-m-d H:i') }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('admin.retake-test-markazi.show', $g->id) }}"
                                               class="inline-flex items-center px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-semibold hover:bg-blue-700">{{ __("Ochish") }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-slate-100 bg-slate-50/70">{{ $groups->links() }}</div>
                    @endif
                </div>
            </form>
        @else
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-white">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">{{ __("Testga yuborilgan talabalar") }}</h4>
                                <p class="text-xs text-slate-500 mt-1">{{ __("Individual yuborilgan talabalar, ularning fani va baholari.") }}</p>
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ __("Jami") }}: <span class="font-bold text-slate-900">{{ $sentApplications->total() }}</span>
                            </div>
                        </div>

                        <form method="GET" action="{{ route('admin.retake-test-markazi.index') }}" class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
                            <input type="hidden" name="tab" value="students">
                            <input type="text"
                                   name="student_search"
                                   value="{{ $filters['student_search'] }}"
                                   placeholder="{{ __('ID yoki ism...') }}"
                                   class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">

                            <select name="level_name" class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">{{ __("Barcha kurslar") }}</option>
                                @foreach($filterOptions['levels'] as $level)
                                    <option value="{{ $level }}" @selected($filters['level_name'] === $level)>{{ $level }}</option>
                                @endforeach
                            </select>

                            <select name="group_name" class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">{{ __("Barcha guruhlar") }}</option>
                                @foreach($filterOptions['groups'] as $groupName)
                                    <option value="{{ $groupName }}" @selected($filters['group_name'] === $groupName)>{{ $groupName }}</option>
                                @endforeach
                            </select>

                            <select name="subject_id" class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">{{ __("Barcha fanlar") }}</option>
                                @foreach($filterOptions['subjects'] as $subject)
                                    <option value="{{ $subject['id'] }}" @selected((string) $filters['subject_id'] === (string) $subject['id'])>{{ $subject['name'] }}</option>
                                @endforeach
                            </select>

                            <select name="semester_name" class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">{{ __("Barcha semesterlar") }}</option>
                                @foreach($filterOptions['semesters'] as $semester)
                                    <option value="{{ $semester }}" @selected($filters['semester_name'] === $semester)>{{ $semester }}</option>
                                @endforeach
                            </select>

                            <div class="flex items-center gap-2">
                                <button type="submit"
                                        class="inline-flex flex-1 justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                    {{ __("Filter") }}
                                </button>
                                <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'students']) }}"
                                   class="inline-flex justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                                    {{ __("Tozalash") }}
                                </a>
                            </div>
                        </form>
                    </div>
                @if($sentApplications->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-500">
                        {{ __("Filter bo'yicha testga yuborilgan talaba topilmadi") }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-100/80">
                            <tr>
                                <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">
                                    <input type="checkbox" id="select-all-sent-students" class="rounded border-gray-300">
                                </th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">#</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("F.I.Sh") }}</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("Kurs") }}</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("Guruh") }}</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("Fan") }}</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("Semester") }}</th>
                                <th class="px-4 py-3 text-center text-[11px] font-bold text-slate-500 uppercase">JN</th>
                                <th class="px-4 py-3 text-center text-[11px] font-bold text-slate-500 uppercase">MT</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">{{ __("Yuborilgan") }}</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-100">
                            @foreach($sentApplications as $app)
                                @php
                                    $student = $app->group?->student;
                                    $retakeGroup = $app->retakeGroup;
                                    $mustaqil = $mustaqilMap[$app->id] ?? null;
                                @endphp
                                <tr class="hover:bg-emerald-50/40 transition">
                                    <td class="px-4 py-3 text-sm text-slate-500">
                                        <input type="checkbox" name="sent_application_ids[]" value="{{ $app->id }}" class="sent-student-checkbox rounded border-gray-300">
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-500">{{ ($sentApplications->currentPage() - 1) * $sentApplications->perPage() + $loop->iteration }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-950 font-bold">
                                        {{ $student?->full_name ?? '—' }}
                                        <span class="block text-[11px] text-slate-400 mt-0.5">{{ $app->student_hemis_id }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $student?->level_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $student?->group_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-900 font-semibold">
                                        {{ $retakeGroup?->subject_name ?? $app->subject_name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $retakeGroup?->semester_name ?? $app->semester_name }}</td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="inline-flex min-w-12 justify-center rounded-lg bg-blue-50 px-2.5 py-1 font-bold text-blue-700">
                                            {{ $app->joriy_score !== null ? rtrim(rtrim(number_format($app->joriy_score, 2, '.', ''), '0'), '.') : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="inline-flex min-w-12 justify-center rounded-lg bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700">
                                            {{ $mustaqil?->grade !== null ? rtrim(rtrim(number_format($mustaqil->grade, 2, '.', ''), '0'), '.') : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">
                                        {{ $app->sent_to_test_markazi_at?->format('Y-m-d H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-t border-slate-100 bg-slate-50/70">{{ $sentApplications->links() }}</div>
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
                    selectAll.addEventListener('change', function () {
                        items.forEach(cb => cb.checked = selectAll.checked);
                    });
                }

                const selectAllStudents = document.getElementById('select-all-sent-students');
                const studentItems = Array.from(document.querySelectorAll('.sent-student-checkbox'));
                if (selectAllStudents && studentItems.length) {
                    selectAllStudents.addEventListener('change', function () {
                        studentItems.forEach(cb => cb.checked = selectAllStudents.checked);
                    });
                }
            });
        </script>
    @endpush
</x-teacher-app-layout>
