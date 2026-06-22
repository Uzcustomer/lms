<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish - Test markazi") }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="mb-4 flex items-center gap-2 border-b border-gray-200">
            <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'groups']) }}"
               class="px-4 py-2 text-sm font-semibold border-b-2 {{ $activeTab === 'groups' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                {{ __("Testga yuborilgan guruhlar") }}
            </a>
            <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'students']) }}"
               class="px-4 py-2 text-sm font-semibold border-b-2 {{ $activeTab === 'students' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                {{ __("Testga yuborilgan talabalar") }}
            </a>
        </div>

        @if($activeTab === 'groups')
            <form method="POST" action="{{ route('admin.retake-test-markazi.generate-yn-oldi-word') }}" id="retake-yn-word-form">
                @csrf
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    @if($groups->isEmpty())
                        <div class="p-10 text-center text-sm text-gray-500">
                            {{ __("Hozircha test markaziga yuborilgan guruhlar yo'q") }}
                        </div>
                    @else
                        <div class="p-3 border-b border-gray-100 flex items-center justify-between gap-3">
                            <div class="text-sm text-gray-600">
                                {{ __("Tanlangan guruhlar uchun YN oldi Word hosil qiling") }}
                            </div>
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                                {{ __("YN oldi word") }}
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">
                                        <input type="checkbox" id="select-all-retake-yn" class="rounded border-gray-300">
                                    </th>
                                    <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Guruh") }}</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fan") }}</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("O'qituvchi") }}</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Tur") }}</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("OSKE / TEST sanasi") }}</th>
                                    <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Talabalar") }}</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Yuborilgan") }}</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($groups as $g)
                                    @php
                                        $atypeLabels = [
                                            'oske' => 'OSKE',
                                            'test' => 'TEST',
                                            'oske_test' => 'OSKE + TEST',
                                        ];
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2.5 text-sm text-gray-700">
                                            <input type="checkbox" name="group_ids[]" value="{{ $g->id }}" class="retake-yn-group-checkbox rounded border-gray-300">
                                        </td>
                                        <td class="px-3 py-2.5 text-sm text-gray-900 font-medium">{{ $g->name }}</td>
                                        <td class="px-3 py-2.5 text-sm text-gray-700">
                                            {{ $g->subject_name }}
                                            <span class="block text-[11px] text-gray-500">{{ $g->semester_name }}</span>
                                        </td>
                                        <td class="px-3 py-2.5 text-sm text-gray-700">{{ $g->teacher_name ?? '—' }}</td>
                                        <td class="px-3 py-2.5 text-sm text-gray-700">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-800">
                                                {{ $atypeLabels[$g->assessment_type] ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs text-gray-700">
                                            @if($g->oske_date)OSKE: {{ $g->oske_date->format('Y-m-d') }}@endif
                                            @if($g->oske_date && $g->test_date)<br>@endif
                                            @if($g->test_date)TEST: {{ $g->test_date->format('Y-m-d') }}@endif
                                        </td>
                                        <td class="px-3 py-2.5 text-sm text-gray-700 text-right">{{ $g->students_count }}</td>
                                        <td class="px-3 py-2.5 text-xs text-gray-500">
                                            {{ $g->sent_to_test_markazi_at?->format('Y-m-d H:i') }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right">
                                            <a href="{{ route('admin.retake-test-markazi.show', $g->id) }}"
                                               class="text-xs text-blue-600 hover:underline">{{ __("Ochish") }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-gray-100">{{ $groups->links() }}</div>
                    @endif
                </div>
            </form>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                @if($sentApplications->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-500">
                        {{ __("Hozircha testga yuborilgan talaba yo'q") }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">#</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("F.I.Sh") }}</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Kurs") }}</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Guruh") }}</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fan") }}</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Semester") }}</th>
                                <th class="px-3 py-2 text-center text-[11px] font-medium text-gray-500 uppercase">JN</th>
                                <th class="px-3 py-2 text-center text-[11px] font-medium text-gray-500 uppercase">MT</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Yuborilgan") }}</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($sentApplications as $app)
                                @php
                                    $student = $app->group?->student;
                                    $retakeGroup = $app->retakeGroup;
                                    $mustaqil = $mustaqilMap[$app->id] ?? null;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2.5 text-sm text-gray-600">{{ ($sentApplications->currentPage() - 1) * $sentApplications->perPage() + $loop->iteration }}</td>
                                    <td class="px-3 py-2.5 text-sm text-gray-900 font-semibold">
                                        {{ $student?->full_name ?? '—' }}
                                        <span class="block text-[11px] text-gray-500">{{ $app->student_hemis_id }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-gray-700">{{ $student?->level_name ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-sm text-gray-700">{{ $student?->group_name ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-sm text-gray-900 font-medium">
                                        {{ $retakeGroup?->subject_name ?? $app->subject_name }}
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-gray-700">{{ $retakeGroup?->semester_name ?? $app->semester_name }}</td>
                                    <td class="px-3 py-2.5 text-sm text-center font-semibold text-blue-700">
                                        {{ $app->joriy_score !== null ? rtrim(rtrim(number_format($app->joriy_score, 2, '.', ''), '0'), '.') : '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-center font-semibold text-emerald-700">
                                        {{ $mustaqil?->grade !== null ? rtrim(rtrim(number_format($mustaqil->grade, 2, '.', ''), '0'), '.') : '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-xs text-gray-500">
                                        {{ $app->sent_to_test_markazi_at?->format('Y-m-d H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-t border-gray-100">{{ $sentApplications->links() }}</div>
                @endif
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const selectAll = document.getElementById('select-all-retake-yn');
                const items = Array.from(document.querySelectorAll('.retake-yn-group-checkbox'));
                if (!selectAll || !items.length) return;

                selectAll.addEventListener('change', function () {
                    items.forEach(cb => cb.checked = selectAll.checked);
                });
            });
        </script>
    @endpush
</x-teacher-app-layout>
