<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish jurnali") }}
        </h2>
    </x-slot>

    @include('partials._journal_table_styles')

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="overflow: visible;">
            {{-- Cascading filtrlar (Ta'lim turi → Fakultet → Yo'nalish → Kurs → Semestr → Guruh + Fan) --}}
            @include('partials._retake_filters', [
                'formAction' => route('admin.retake-journal.index'),
                'educationTypes' => $educationTypes ?? collect(),
                'subjects' => $subjects ?? collect(),
                'hiddenFilters' => ['full_name'],
            ])

            <div class="overflow-x-auto">
                @if($groups->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-500">
                        {{ __("Hozircha qayta o'qish guruhlari yo'q") }}
                    </div>
                @else
                    @php
                        $statusBadge = [
                            'forming' => 'badge-gray',
                            'scheduled' => 'badge-blue',
                            'in_progress' => 'badge-green',
                            'completed' => 'badge-purple',
                        ];
                    @endphp
                    <table class="journal-table">
                        <thead>
                        <tr>
                            <th class="th-num">#</th>
                            <th>{{ __("Guruh") }}</th>
                            <th>{{ __("Fan") }}</th>
                            <th>{{ __("Semestr") }}</th>
                            <th>{{ __("O'qituvchi") }}</th>
                            <th>{{ __("Sanalar") }}</th>
                            <th style="text-align:center;">{{ __("Talabalar") }}</th>
                            <th>{{ __("Holat") }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($groups as $index => $g)
                            <tr onclick="window.location='{{ route('admin.retake-journal.show', $g->id) }}'">
                                <td class="td-num">{{ $groups->firstItem() + $index }}</td>
                                <td><span class="badge badge-indigo">{{ $g->name }}</span></td>
                                <td><span class="text-cell text-subject">{{ $g->subject_name }}</span></td>
                                <td><span class="badge badge-teal">{{ $g->semester_name ?? '—' }}</span></td>
                                <td><span class="text-cell text-emerald">{{ $g->teacher_name ?? '—' }}</span></td>
                                <td>
                                    <span class="badge badge-violet">{{ $g->start_date?->format('Y-m-d') }}</span>
                                    <span class="text-gray-400">→</span>
                                    <span class="badge badge-violet">{{ $g->end_date?->format('Y-m-d') }}</span>
                                </td>
                                <td style="text-align:center;"><span class="badge badge-blue">{{ $g->students_count }}</span></td>
                                <td>
                                    <span class="badge {{ $statusBadge[$g->status] ?? 'badge-gray' }}">{{ $g->statusLabel() }}</span>
                                </td>
                                <td style="text-align:right;" onclick="event.stopPropagation();">
                                    <a href="{{ route('admin.retake-journal.show', $g->id) }}" class="journal-row-link">{{ __("Jurnal") }}</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                        {{ $groups->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-teacher-app-layout>
