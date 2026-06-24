<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish - Test markazi") }}
        </h2>
    </x-slot>

    @include('partials._journal_table_styles')

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="mb-4 flex items-center gap-2 border-b border-gray-200 bg-white rounded-t-xl px-4 pt-3 shadow-sm">
            <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'groups']) }}"
               class="px-5 py-3 text-sm font-semibold border-b-2 rounded-t-lg transition {{ $activeTab === 'groups' ? 'border-blue-600 text-blue-700 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">
                {{ __("Testga yuborilgan guruhlar") }}
            </a>
            <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'students']) }}"
               class="px-5 py-3 text-sm font-semibold border-b-2 rounded-t-lg transition {{ $activeTab === 'students' ? 'border-blue-600 text-blue-700 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">
                {{ __("Testga yuborilgan talabalar") }}
            </a>
        </div>

        @php
            $atypeBadge = [
                'oske' => ['label' => 'OSKE', 'cls' => 'badge-blue'],
                'test' => ['label' => 'TEST', 'cls' => 'badge-green'],
                'oske_test' => ['label' => 'OSKE + TEST', 'cls' => 'badge-purple'],
            ];
        @endphp

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
                            <table class="journal-table">
                                <thead>
                                <tr>
                                    <th class="th-num" onclick="event.stopPropagation();">
                                        <input type="checkbox" id="select-all-retake-yn" class="rounded border-gray-300">
                                    </th>
                                    <th>{{ __("Guruh") }}</th>
                                    <th>{{ __("Fan") }}</th>
                                    <th>{{ __("O'qituvchi") }}</th>
                                    <th>{{ __("Tur") }}</th>
                                    <th>{{ __("OSKE / TEST sanasi") }}</th>
                                    <th style="text-align:center;">{{ __("Talabalar") }}</th>
                                    <th>{{ __("Yuborilgan") }}</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($groups as $g)
                                    <tr onclick="window.location='{{ route('admin.retake-test-markazi.show', $g->id) }}'">
                                        <td class="td-num" onclick="event.stopPropagation();">
                                            <input type="checkbox" name="group_ids[]" value="{{ $g->id }}" class="retake-yn-group-checkbox rounded border-gray-300">
                                        </td>
                                        <td><span class="badge badge-indigo">{{ $g->name }}</span></td>
                                        <td>
                                            <span class="text-cell text-subject">{{ $g->subject_name }}</span>
                                            <span class="text-cell" style="color:#64748b;font-size:11px;">{{ $g->semester_name }}</span>
                                        </td>
                                        <td><span class="text-cell text-emerald">{{ $g->teacher_name ?? '—' }}</span></td>
                                        <td>
                                            @php $b = $atypeBadge[$g->assessment_type] ?? ['label' => '—', 'cls' => 'badge-gray']; @endphp
                                            <span class="badge {{ $b['cls'] }}">{{ $b['label'] }}</span>
                                        </td>
                                        <td class="text-xs text-gray-700">
                                            @if($g->oske_date)<span class="badge badge-violet">OSKE: {{ $g->oske_date->format('Y-m-d') }}</span>@endif
                                            @if($g->test_date)<span class="badge badge-violet">TEST: {{ $g->test_date->format('Y-m-d') }}</span>@endif
                                            @if(!$g->oske_date && !$g->test_date)—@endif
                                        </td>
                                        <td style="text-align:center;"><span class="badge badge-blue">{{ $g->students_count }}</span></td>
                                        <td class="text-xs text-gray-500">{{ $g->sent_to_test_markazi_at?->format('Y-m-d H:i') }}</td>
                                        <td style="text-align:right;" onclick="event.stopPropagation();">
                                            <a href="{{ route('admin.retake-test-markazi.show', $g->id) }}" class="journal-row-link">{{ __("Ochish") }}</a>
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
                <div class="p-3 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                    <form method="GET" action="{{ route('admin.retake-test-markazi.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="students">
                        <input type="text"
                               name="student_search"
                               value="{{ $studentSearch }}"
                               placeholder="{{ __('Ism yoki ID...') }}"
                               class="w-64 rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-semibold hover:bg-blue-700">
                            {{ __("Qidirish") }}
                        </button>
                        @if($studentSearch !== '')
                            <a href="{{ route('admin.retake-test-markazi.index', ['tab' => 'students']) }}"
                               class="text-sm text-gray-500 hover:text-gray-800">{{ __("Tozalash") }}</a>
                        @endif
                    </form>

                    <a href="{{ route('admin.retake-test-markazi.daily-allowed-students-word', ['student_search' => $studentSearch]) }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                        {{ __("Word chiqarish") }}
                    </a>
                </div>
                @if($sentApplications->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-500">
                        {{ __("Hozircha testga yuborilgan talaba yo'q") }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="journal-table">
                            <thead>
                            <tr>
                                <th class="th-num">#</th>
                                <th>{{ __("F.I.Sh") }}</th>
                                <th>{{ __("Kurs") }}</th>
                                <th>{{ __("Guruh") }}</th>
                                <th>{{ __("Fan") }}</th>
                                <th>{{ __("Semestr") }}</th>
                                <th style="text-align:center;">JN</th>
                                <th style="text-align:center;">MT</th>
                                <th>{{ __("Yuborilgan") }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($sentApplications as $app)
                                @php
                                    $student = $app->group?->student;
                                    $retakeGroup = $app->retakeGroup;
                                    $mustaqil = $mustaqilMap[$app->id] ?? null;
                                    $rgId = $app->retake_group_id ?? $retakeGroup?->id;
                                @endphp
                                <tr @if($rgId) onclick="window.location='{{ route('admin.retake-test-markazi.show', $rgId) }}'" @endif>
                                    <td class="td-num">{{ ($sentApplications->currentPage() - 1) * $sentApplications->perPage() + $loop->iteration }}</td>
                                    <td>
                                        <span class="text-cell text-subject">{{ $student?->full_name ?? '—' }}</span>
                                        <span class="text-cell" style="color:#64748b;font-size:11px;">{{ $app->student_hemis_id }}</span>
                                    </td>
                                    <td><span class="badge badge-violet">{{ $student?->level_name ?? '—' }}</span></td>
                                    <td><span class="badge badge-indigo">{{ $student?->group_name ?? '—' }}</span></td>
                                    <td><span class="text-cell text-subject">{{ $retakeGroup?->subject_name ?? $app->subject_name }}</span></td>
                                    <td><span class="badge badge-teal">{{ $retakeGroup?->semester_name ?? $app->semester_name }}</span></td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-blue">{{ $app->joriy_score !== null ? rtrim(rtrim(number_format($app->joriy_score, 2, '.', ''), '0'), '.') : '—' }}</span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-green">{{ $mustaqil?->grade !== null ? rtrim(rtrim(number_format($mustaqil->grade, 2, '.', ''), '0'), '.') : '—' }}</span>
                                    </td>
                                    <td class="text-xs text-gray-500">{{ $app->sent_to_test_markazi_at?->format('Y-m-d H:i') }}</td>
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
